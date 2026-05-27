<?php

declare(strict_types=1);

namespace Pagnow;

/**
 * Payouts (a.k.a. withdrawals) — move money OUT of your PagNow wallet to a PIX
 * key, bank account, or crypto address. Amounts are in the smallest currency
 * unit (cents/centavos).
 *
 * A created payout starts as PENDING. Whether it dispatches immediately or
 * waits for manual review is controlled per-tenant by PagNow (the "auto-payout"
 * setting). Track the outcome via `withdrawal.*` webhook events or retrieve().
 */
final class Payouts
{
    public function __construct(private readonly PagNow $client) {}

    /**
     * Request a payout. Validates funds + PIX key server-side.
     *
     * Omit `walletId` to auto-select the wallet (currency default, else any
     * spendable wallet with balance); pass it to debit a specific wallet.
     *
     * @param array{
     *   type:string, amount:int, currency?:string, walletId?:string,
     *   pixKey?:string, pixKeyType?:string, bankCode?:string, bankAgency?:string, bankAccount?:string,
     *   cryptoAddress?:string, cryptoNetwork?:string, cryptoCurrency?:string
     * } $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        if (empty($input['type'])) {
            throw new \InvalidArgumentException('PagNow: payouts.create requires type');
        }
        if (!isset($input['amount']) || (int) $input['amount'] <= 0) {
            throw new \InvalidArgumentException('PagNow: payouts.create requires a positive amount (cents)');
        }
        return $this->client->request('POST', '/v1/withdrawals', $input) ?? [];
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/withdrawals/' . rawurlencode($id)) ?? [];
    }

    /**
     * @param array<string, scalar> $query page/limit/status
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $qs = $query ? '?' . http_build_query($query) : '';
        return $this->client->request('GET', '/v1/withdrawals' . $qs) ?? [];
    }
}
