<?php

namespace App\Services;

use App\Models\Monitor;
use App\Services\CheckEngine\CheckResult;
use App\Services\Ssrf\SsrfViolationException;
use App\Services\Ssrf\ValidatedTarget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call a user-supplied URL (PRD §16.5).
 *
 * Every hop is validated by SsrfGuard, the connection is pinned to the
 * validated IP, redirects are followed manually so each one is re-validated,
 * and the response body is read through a hard size cap.
 */
class CheckService
{
    public function __construct(private readonly SsrfGuard $guard) {}

    public function run(Monitor $monitor): CheckResult
    {
        $startedAt = microtime(true);
        $url = $monitor->url;
        $maxRedirects = (int) config('pupwatch.max_redirects', 5);
        $redirects = 0;

        try {
            while (true) {
                $target = $this->guard->validate($url);

                $response = $this->send($monitor, $target);
                $status = $response->status();

                if ($monitor->follow_redirects && $this->isRedirect($status)) {
                    $location = (string) $response->header('Location');
                    $this->closeBody($response);

                    if ($location === '') {
                        return CheckResult::failure(
                            CheckResult::UNKNOWN,
                            "The server returned {$status} without a Location header.",
                            $status,
                            $this->elapsedMs($startedAt),
                        );
                    }

                    if ($redirects >= $maxRedirects) {
                        return CheckResult::failure(
                            CheckResult::TOO_MANY_REDIRECTS,
                            "More than {$maxRedirects} redirects.",
                            $status,
                            $this->elapsedMs($startedAt),
                        );
                    }

                    $url = $this->absoluteUrl($url, $location);
                    $redirects++;

                    continue;
                }

                $elapsed = $this->elapsedMs($startedAt);

                if (! $this->statusAccepted($monitor, $status)) {
                    $this->closeBody($response);

                    return CheckResult::failure(
                        CheckResult::UNEXPECTED_STATUS,
                        sprintf('Expected %s, got %d.', $monitor->expected_status, $status),
                        $status,
                        $elapsed,
                    );
                }

                if ($monitor->keyword !== null && $monitor->keyword !== '') {
                    $body = $this->readBody($response, (int) config('pupwatch.max_body_bytes', 1048576));
                    $found = str_contains($body, $monitor->keyword);

                    if ($monitor->keyword_mode === Monitor::KEYWORD_MUST_CONTAIN && ! $found) {
                        return CheckResult::failure(
                            CheckResult::KEYWORD_MISSING,
                            'The keyword was not found in the response body.',
                            $status,
                            $elapsed,
                        );
                    }

                    if ($monitor->keyword_mode === Monitor::KEYWORD_MUST_NOT_CONTAIN && $found) {
                        return CheckResult::failure(
                            CheckResult::KEYWORD_FOUND,
                            'The keyword was found in the response body.',
                            $status,
                            $elapsed,
                        );
                    }
                } else {
                    // PRD §4.3 — never read a body we do not need.
                    $this->closeBody($response);
                }

                return new CheckResult(true, $status, $elapsed);
            }
        } catch (SsrfViolationException $e) {
            return CheckResult::failure(CheckResult::BLOCKED_TARGET, $e->getMessage(), null, $this->elapsedMs($startedAt));
        } catch (ConnectionException $e) {
            return CheckResult::failure(
                $this->classifyConnectionError($e->getMessage()),
                $e->getMessage(),
                null,
                $this->elapsedMs($startedAt),
            );
        }
    }

    private function send(Monitor $monitor, ValidatedTarget $target): Response
    {
        $headers = array_merge(
            ['User-Agent' => (string) config('pupwatch.user_agent')],
            $monitor->custom_headers ?? [],
        );

        return Http::withOptions([
            'timeout' => $monitor->timeout_seconds,
            'connect_timeout' => min(5, $monitor->timeout_seconds),
            'allow_redirects' => false,
            'http_errors' => false,
            'stream' => true,
            'curl' => [
                // PRD §5.4 — pin the connection to the validated IP, which
                // defeats DNS rebinding between validation and request time.
                CURLOPT_RESOLVE => [$target->resolveEntry()],
            ],
        ])->withHeaders($headers)->send($monitor->method, $target->url);
    }

    private function isRedirect(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    private function statusAccepted(Monitor $monitor, int $status): bool
    {
        foreach ($monitor->expectedStatusRanges() as [$low, $high]) {
            if ($status >= $low && $status <= $high) {
                return true;
            }
        }

        return false;
    }

    private function readBody(Response $response, int $cap): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        try {
            while (! $stream->eof() && strlen($body) < $cap) {
                $chunk = $stream->read(min(8192, $cap - strlen($body)));

                if ($chunk === '') {
                    break;
                }

                $body .= $chunk;
            }
        } finally {
            $stream->close();
        }

        return $body;
    }

    private function closeBody(Response $response): void
    {
        try {
            $response->toPsrResponse()->getBody()->close();
        } catch (\Throwable) {
            // Nothing to release.
        }
    }

    /**
     * Resolve a Location header against the URL that produced it.
     */
    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $location;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1) ?: '/';

        return $origin.$directory.$location;
    }

    private function classifyConnectionError(string $message): string
    {
        $haystack = strtolower($message);

        return match (true) {
            str_contains($haystack, 'timed out'), str_contains($haystack, 'timeout') => CheckResult::TIMEOUT,
            str_contains($haystack, 'could not resolve'), str_contains($haystack, 'name or service not known'),
            str_contains($haystack, 'getaddrinfo') => CheckResult::DNS_FAILURE,
            str_contains($haystack, 'refused') => CheckResult::CONNECTION_REFUSED,
            str_contains($haystack, 'ssl'), str_contains($haystack, 'certificate'),
            str_contains($haystack, 'tls') => CheckResult::SSL_ERROR,
            default => CheckResult::UNKNOWN,
        };
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
