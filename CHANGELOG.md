# Changelog — pagnow/sdk-php

Versions are tracked via git tags on Packagist (no `version` field in composer.json).

---

## Unreleased (2026-05-28) — parity with @pagnow/sdk-node

### Payments
- `providerStatus($id)` — GET `/v1/payments/{id}/provider-status`
- `receipt($id)` — GET `/v1/payments/{id}/receipt`
- `reconcile($id)` — POST `/v1/payments/{id}/reconcile`
- `resendWebhook($id)` — POST `/v1/payments/{id}/resend-webhook`
- `list()` now accepts `currency` in the query array
- `create()` PHPDoc expanded: added `items`, `country`, `riskTier`, `environment`
- `refund()` PHPDoc expanded: added `refundId` field

### Wallets
- `getBalance(?string $currency)` — GET `/v1/wallets/balance[?currency=]`
- `getSummary()` — GET `/v1/wallets/summary`
- `getStatement(array $query)` — GET `/v1/wallets/statement`
- `getBalanceHistory(array $query)` — GET `/v1/wallets/balance-history`
- `create(array $data)` — POST `/v1/wallets` (`currency`, `walletType`, `label`)

### Payouts
- `validatePixKey(array $data)` — POST `/v1/withdrawals/validate-pix-key`
  Body: `{ key: string, type?: CPF|CNPJ|EMAIL|PHONE|RANDOM }`

### Webhooks
- `Webhooks` constructor now accepts an optional `?PagNow $client` parameter.
  `PagNow.php` injects the client so the full HTTP surface is available via
  `$pagnow->webhooks->*`. Existing `verify()`/`parse()` usage is unchanged.
- `verify()` now accepts both `sha256=<hex>` and bare-hex signatures
- New HTTP management methods (all under `/v1/webhooks`):
  - `createEndpoint(array $data)` — POST `/v1/webhooks/endpoints`
  - `listEndpoints()` — GET `/v1/webhooks/endpoints`
  - `updateEndpoint($id, array $data)` — PATCH `/v1/webhooks/endpoints/{id}`
  - `deleteEndpoint($id)` — DELETE `/v1/webhooks/endpoints/{id}`
  - `rotateSecret($id)` — POST `/v1/webhooks/endpoints/{id}/rotate-secret`
  - `listDeliveries(array $query)` — GET `/v1/webhooks/deliveries`
  - `getDelivery($id)` — GET `/v1/webhooks/deliveries/{id}`
  - `replayDelivery($id)` — POST `/v1/webhooks/deliveries/{id}/replay`
  - `replayBulk(array $data)` — POST `/v1/webhooks/deliveries/replay-bulk`
  - `eventCatalog()` — GET `/v1/webhooks/event-catalog`
  - `stats()` — GET `/v1/webhooks/stats`
