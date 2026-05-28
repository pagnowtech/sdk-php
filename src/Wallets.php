<?php

declare(strict_types=1);

namespace Pagnow;

/**
 * Wallets — read your balances, statement and history; create new wallets.
 *
 * A tenant may hold multiple spendable wallets per currency; one is the
 * default (the payout fallback when Payouts::create is called without a
 * walletId). Wallet creation is done by PagNow (admin) but also exposed here
 * for programmatic provisioning.
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

    /**
     * Get wallet balances for the current tenant. The response includes a
     * legacy flat `{ available, pending, total, currency }` shape (the primary
     * currency, defaulting to BRL) plus a `balances` array covering every
     * currency. Pass `$currency` to get the legacy single-currency flat shape.
     *
     * @return array<string, mixed>
     */
    public function getBalance(?string $currency = null): array
    {
        $qs = $currency !== null ? '?currency=' . rawurlencode($currency) : '';
        return $this->client->request('GET', '/v1/wallets/balance' . $qs) ?? [];
    }

    /**
     * Account money summary per currency (available, pending release, total
     * withdrawn).
     *
     * @return list<array<string, mixed>>
     */
    public function getSummary(): array
    {
        $res = $this->client->request('GET', '/v1/wallets/summary');
        return is_array($res) ? array_values($res) : [];
    }

    /**
     * Get paginated wallet statement (ledger movements).
     *
     * @param array<string, scalar> $query page/limit/type/currency/startDate/endDate/referenceId
     * @return array<string, mixed>
     */
    public function getStatement(array $query = []): array
    {
        $qs = $query ? '?' . http_build_query($query) : '';
        return $this->client->request('GET', '/v1/wallets/statement' . $qs) ?? [];
    }

    /**
     * Get daily wallet balance history.
     *
     * @param array<string, scalar> $query currency/startDate/endDate/page/limit
     * @return array<string, mixed>
     */
    public function getBalanceHistory(array $query = []): array
    {
        $qs = $query ? '?' . http_build_query($query) : '';
        return $this->client->request('GET', '/v1/wallets/balance-history' . $qs) ?? [];
    }

    /**
     * Create a new wallet for the tenant.
     *
     * @param array{currency:string, walletType:string, label?:string} $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        if (empty($data['currency'])) {
            throw new \InvalidArgumentException('PagNow: wallets.create requires currency');
        }
        if (empty($data['walletType'])) {
            throw new \InvalidArgumentException('PagNow: wallets.create requires walletType');
        }
        return $this->client->request('POST', '/v1/wallets', $data) ?? [];
    }
}
