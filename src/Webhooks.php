<?php

declare(strict_types=1);

namespace Pagnow;

/**
 * Webhook helpers. PagNow signs each delivery with
 *   X-PagNow-Signature: sha256=<hmac_hex>
 * where the HMAC-SHA256 is computed over the RAW request body using your
 * endpoint secret. Always verify against the raw body (not a re-encoded one).
 */
final class Webhooks
{
    /**
     * Constant-time verification of a delivery's signature.
     *
     * @param string $rawBody   the exact bytes you received
     * @param string $signature value of the X-PagNow-Signature header
     * @param string $secret    your endpoint's signing secret
     */
    public function verify(string $rawBody, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify then decode the event. Throws on an invalid signature.
     *
     * @return array<string, mixed>
     */
    public function parse(string $rawBody, string $signature, string $secret): array
    {
        if (!$this->verify($rawBody, $signature, $secret)) {
            throw new \RuntimeException('PagNow: invalid webhook signature');
        }
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
