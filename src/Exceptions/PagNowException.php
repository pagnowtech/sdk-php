<?php

declare(strict_types=1);

namespace Pagnow\Exceptions;

/**
 * Base error for every non-2xx PagNow response. Mirrors the API's stable
 * error envelope: { statusCode, error, message, fieldErrors?, requestId }.
 */
class PagNowException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $fieldErrors field => reasons (on 400)
     * @param array<string, mixed>|null    $body        raw decoded response body
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $requestId = null,
        public readonly array $fieldErrors = [],
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }
}

/** 401 / 403 — apikey missing/invalid or tenant suspended. */
class PagNowAuthException extends PagNowException {}

/** 409 — idempotencyKey reused with a different body. Use a fresh key. */
class PagNowConflictException extends PagNowException {}

/** 400 / 422 — validation/business rule. Inspect $fieldErrors. */
class PagNowValidationException extends PagNowException {}

/** Transport failure (timeout, DNS, connection). */
class PagNowNetworkException extends PagNowException {}
