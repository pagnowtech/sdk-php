<?php

declare(strict_types=1);

namespace Pagnow;

/**
 * Wallets — read your balances. A tenant may hold multiple spendable wallets
 * per currency; one is the default (the payout fallback when Payouts::create
 * is called without a walletId). Wallet creation is done by PagNow (admin).
 */
final class Wallets
{
    public function __construct(private readonly PagNow $client) {}

    /**
     * List your wallets with balances (smallest currency unit).
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $res = $this->client->request('GET', '/v1/wallets');
        return is_array($res) ? array_values($res) : [];
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/wallets/' . rawurlencode($id)) ?? [];
    }
}
