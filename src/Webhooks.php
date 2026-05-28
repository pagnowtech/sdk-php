<?php

declare(strict_types=1);

namespace Pagnow;

/**
 * Webhooks — two surfaces in one class:
 *
 * 1. Signature verification utilities (no HTTP):
 *    verify($rawBody, $signature, $secret)  — constant-time HMAC check
 *    parse($rawBody, $signature, $secret)   — verify + JSON-decode
 *
 * 2. HTTP management API (requires a configured PagNow client):
 *    Endpoint CRUD + secret rotation:
 *      createEndpoint($data)         POST /v1/webhooks/endpoints
 *      listEndpoints()               GET  /v1/webhooks/endpoints
 *      updateEndpoint($id, $data)    PATCH /v1/webhooks/endpoints/{id}
 *      deleteEndpoint($id)           DELETE /v1/webhooks/endpoints/{id}
 *      rotateSecret($id)             POST /v1/webhooks/endpoints/{id}/rotate-secret
 *    Delivery inspection + replay:
 *      listDeliveries($query)        GET  /v1/webhooks/deliveries
 *      getDelivery($id)              GET  /v1/webhooks/deliveries/{id}
 *      replayDelivery($id)           POST /v1/webhooks/deliveries/{id}/replay
 *      replayBulk($data)             POST /v1/webhooks/deliveries/replay-bulk
 *    Metadata:
 *      eventCatalog()                GET  /v1/webhooks/event-catalog
 *      stats()                       GET  /v1/webhooks/stats
 *
 * When constructed without a client (verify-only usage) the HTTP methods throw
 * a descriptive RuntimeException on first call; verify/parse always work.
 *
 * PagNow.php injects the client so all surfaces are available via
 * $pagnow->webhooks->createEndpoint(...) etc.
 *
 * Signature header: X-PagNow-Signature: sha256=<hmac_hex>
 * Both the `sha256=<hex>` and bare-hex forms are accepted by verify/parse.
 */
final class Webhooks
{
    private readonly ?PagNow $client;

    public function __construct(?PagNow $client = null)
    {
        $this->client = $client;
    }

    // ── Signature helpers ────────────────────────────────────────────────────

    /**
     * Constant-time verification of a delivery's signature.
     *
     * Accepts both `sha256=<hex>` (the canonical form emitted by PagNow) and a
     * bare hex string (backward compat with old callers).
     *
     * @param string $rawBody   the exact bytes received (do NOT re-encode)
     * @param string $signature value of the X-PagNow-Signature header
     * @param string $secret    your endpoint's signing secret
     */
    public function verify(string $rawBody, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }
        // Strip the `sha256=` prefix if present; accept bare hex too.
        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, strlen('sha256='))
            : $signature;

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $provided);
    }

    /**
     * Verify then decode the event. Throws on an invalid signature.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on signature mismatch
     * @throws \JsonException    on malformed JSON
     */
    public function parse(string $rawBody, string $signature, string $secret): array
    {
        if (!$this->verify($rawBody, $signature, $secret)) {
            throw new \RuntimeException('PagNow: invalid webhook signature');
        }
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    // ── Endpoint management ──────────────────────────────────────────────────

    /**
     * Register a new webhook endpoint. The `secret` in the response is
     * returned ONCE — store it before discarding.
     *
     * @param array{url:string, events:list<string>} $data
     * @return array<string, mixed>
     */
    public function createEndpoint(array $data): array
    {
        if (empty($data['url'])) {
            throw new \InvalidArgumentException('PagNow: webhooks.createEndpoint requires url');
        }
        if (empty($data['events']) || !is_array($data['events'])) {
            throw new \InvalidArgumentException('PagNow: webhooks.createEndpoint requires a non-empty events array');
        }
        return $this->http()->request('POST', '/v1/webhooks/endpoints', $data) ?? [];
    }

    /**
     * List all registered webhook endpoints (secrets not included).
     * @return array<string, mixed>
     */
    public function listEndpoints(): array
    {
        return $this->http()->request('GET', '/v1/webhooks/endpoints') ?? [];
    }

    /**
     * Update url, events, or status on an existing endpoint.
     *
     * @param array{url?:string, events?:list<string>, status?:string} $data
     * @return array<string, mixed>
     */
    public function updateEndpoint(string $id, array $data): array
    {
        return $this->http()->request('PATCH', '/v1/webhooks/endpoints/' . rawurlencode($id), $data) ?? [];
    }

    /**
     * Delete an endpoint permanently.
     * @return array<string, mixed>
     */
    public function deleteEndpoint(string $id): array
    {
        return $this->http()->request('DELETE', '/v1/webhooks/endpoints/' . rawurlencode($id)) ?? [];
    }

    /**
     * Rotate the signing secret for an endpoint. The new secret is returned
     * once — store it. The path param is canonical; body `{id}` is also sent
     * for backward compat with the legacy body-only callers.
     *
     * @return array<string, mixed>
     */
    public function rotateSecret(string $id): array
    {
        return $this->http()->request(
            'POST',
            '/v1/webhooks/endpoints/' . rawurlencode($id) . '/rotate-secret',
            ['id' => $id],
        ) ?? [];
    }

    // ── Delivery management ──────────────────────────────────────────────────

    /**
     * List webhook deliveries with optional filters.
     *
     * @param array<string, scalar> $query status/eventType/endpointId/limit/offset
     * @return array<string, mixed>
     */
    public function listDeliveries(array $query = []): array
    {
        $qs = $query ? '?' . http_build_query($query) : '';
        return $this->http()->request('GET', '/v1/webhooks/deliveries' . $qs) ?? [];
    }

    /**
     * Get a single delivery with logs and endpoint detail.
     * @return array<string, mixed>
     */
    public function getDelivery(string $id): array
    {
        return $this->http()->request('GET', '/v1/webhooks/deliveries/' . rawurlencode($id)) ?? [];
    }

    /**
     * Manually replay a FAILED or CANCELLED delivery. Resets the attempt
     * counter and schedules it for immediate retry. Body `{id}` is also sent
     * for compat with the legacy body-only path.
     *
     * @return array<string, mixed>
     */
    public function replayDelivery(string $id): array
    {
        return $this->http()->request(
            'POST',
            '/v1/webhooks/deliveries/' . rawurlencode($id) . '/replay',
            ['id' => $id],
        ) ?? [];
    }

    /**
     * Bulk replay — re-queues all FAILED/CANCELLED deliveries (optionally
     * filtered). Bounded by `limit` (default 50, max 500).
     *
     * @param array{status?:string, endpointId?:string, limit?:int} $data
     * @return array<string, mixed>
     */
    public function replayBulk(array $data = []): array
    {
        return $this->http()->request('POST', '/v1/webhooks/deliveries/replay-bulk', $data) ?? [];
    }

    // ── Metadata ─────────────────────────────────────────────────────────────

    /**
     * List all subscribable webhook event names and their descriptions.
     * @return list<array<string, mixed>>
     */
    public function eventCatalog(): array
    {
        $res = $this->http()->request('GET', '/v1/webhooks/event-catalog');
        return is_array($res) ? array_values($res) : [];
    }

    /**
     * Delivery counts by status for the current tenant.
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return $this->http()->request('GET', '/v1/webhooks/stats') ?? [];
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function http(): PagNow
    {
        if ($this->client === null) {
            throw new \RuntimeException(
                'PagNow: webhook management methods require a PagNow client. ' .
                'Use new PagNow([\'apiKey\' => ...]) and access $client->webhooks.',
            );
        }
        return $this->client;
    }
}
