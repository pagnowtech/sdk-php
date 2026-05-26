<?php

declare(strict_types=1);

namespace Pagnow;

/**
 * Payments resource — create / retrieve / list / refund / cancel.
 * Amounts are in the smallest currency unit (cents/centavos).
 */
final class Payments
{
    public function __construct(private readonly PagNow $client) {}

    /**
     * Create a payment. Pass `customer` as a nested array; it is flattened to
     * the API's customerName/customerDocument/customerEmail/customerPhone
     * fields on the wire. `idempotencyKey` is required (replays return the
     * original charge).
     *
     * @param array{
     *   amount:int, paymentMethods:list<string>, idempotencyKey:string,
     *   currency?:string, customer?:array{name?:string,document?:string,email?:string,phone?:string},
     *   customerId?:string, webhookUrl?:string, metadata?:array<string,mixed>
     * } $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        if (empty($input['idempotencyKey'])) {
            throw new \InvalidArgumentException('PagNow: payments.create requires idempotencyKey');
        }
        $customer = $input['customer'] ?? [];
        unset($input['customer']);
        $body = $input;
        if (!empty($customer['name'])) {
            $body['customerName'] = $customer['name'];
        }
        if (!empty($customer['document'])) {
            $body['customerDocument'] = $customer['document'];
        }
        if (!empty($customer['email'])) {
            $body['customerEmail'] = $customer['email'];
        }
        if (!empty($customer['phone'])) {
            $body['customerPhone'] = $customer['phone'];
        }

        return $this->client->request('POST', '/v1/payments', $body) ?? [];
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payments/' . rawurlencode($id)) ?? [];
    }

    /**
     * @param array<string, scalar> $query page/limit/status/startDate/endDate/sortBy/sortOrder
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $qs = $query ? '?' . http_build_query($query) : '';
        return $this->client->request('GET', '/v1/payments' . $qs) ?? [];
    }

    /**
     * Refund a settled transaction (full or partial). `idempotencyKey` required.
     *
     * @param array{idempotencyKey:string, amount?:int, reason?:string, strategy?:string, passFeeToTenant?:bool, destinationKey?:string} $input
     * @return array<string, mixed>
     */
    public function refund(string $id, array $input): array
    {
        if (empty($input['idempotencyKey'])) {
            throw new \InvalidArgumentException('PagNow: payments.refund requires idempotencyKey');
        }
        return $this->client->request('POST', '/v1/payments/' . rawurlencode($id) . '/refund', $input) ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function listRefunds(string $id): array
    {
        $res = $this->client->request('GET', '/v1/payments/' . rawurlencode($id) . '/refunds');
        return is_array($res) ? array_values($res) : [];
    }

    /**
     * Cancel an in-flight charge (WAITING_PAYMENT / PENDING / PROCESSING).
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->client->request('POST', '/v1/payments/' . rawurlencode($id) . '/cancel') ?? [];
    }
}
