<?php

namespace App\Services\CheckEngine;

/**
 * The outcome of one check (PRD §4.3). This is plain data: a failed check is a
 * recorded result, never an exception that reaches the queue.
 */
final readonly class CheckResult
{
    public const TIMEOUT = 'timeout';

    public const DNS_FAILURE = 'dns_failure';

    public const CONNECTION_REFUSED = 'connection_refused';

    public const SSL_ERROR = 'ssl_error';

    public const UNEXPECTED_STATUS = 'unexpected_status';

    public const KEYWORD_MISSING = 'keyword_missing';

    public const KEYWORD_FOUND = 'keyword_found';

    public const TOO_MANY_REDIRECTS = 'too_many_redirects';

    public const BLOCKED_TARGET = 'blocked_target';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public bool $success,
        public ?int $statusCode = null,
        public ?int $responseTimeMs = null,
        public ?string $errorType = null,
        public ?string $errorMessage = null,
    ) {}

    public static function failure(string $type, ?string $message, ?int $statusCode = null, ?int $ms = null): self
    {
        return new self(false, $statusCode, $ms, $type, $message === null ? null : mb_substr($message, 0, 255));
    }
}
