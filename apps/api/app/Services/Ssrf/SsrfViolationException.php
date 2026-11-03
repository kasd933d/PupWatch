<?php

namespace App\Services\Ssrf;

use RuntimeException;

/**
 * Thrown when a URL must not be requested (PRD §5). The message is safe to
 * store in an error row: it never contains response data.
 */
class SsrfViolationException extends RuntimeException {}
