# pagnow/sdk-php

Official PHP client for the [PagNow](https://pagnow.com) payments API.
Zero runtime dependencies (native cURL), PHP 8.1+.

```bash
composer require pagnow/sdk-php
```

## Quickstart

```php
use Pagnow\PagNow;

// The apikey identifies your tenant — no tenantId needed.
// baseUrl defaults to https://v2.pagnow.com (the live API host).
$pagnow = new PagNow(['apiKey' => getenv('PAGNOW_API_KEY')]);

$charge = $pagnow->payments->create([
    'amount' => 1990,                 // cents
    'currency' => 'BRL',
    'paymentMethods' => ['PIX'],
    'customer' => [
        'name' => 'João Silva',
        'document' => '52998224725',
        'email' => 'joao@exemplo.com',
    ],
    'idempotencyKey' => "pedido-{$orderId}",
]);

echo $charge['pixCopyPaste'];

// Status, refund (full/partial), cancel:
$status = $pagnow->payments->retrieve($charge['id']);
$pagnow->payments->refund($charge['id'], ['idempotencyKey' => "refund-{$charge['id']}"]);
$pagnow->payments->cancel($charge['id']);
```

## Payouts (withdrawals)

Move money **out** of your wallet to a PIX key, bank account, or crypto address.

```php
$payout = $pagnow->payouts->create([
    'type' => 'PIX',
    'amount' => 10000,            // cents
    'currency' => 'BRL',
    'pixKey' => 'joao@exemplo.com',
    'pixKeyType' => 'EMAIL',
]);

$pagnow->payouts->retrieve($payout['id']);
$pagnow->payouts->list(['status' => 'PENDING']);
```

A payout starts as `PENDING`. Whether it dispatches **immediately** or waits for
**manual review** is controlled per-account by PagNow (the "auto-payout"
setting — ask your account manager to enable it). Track the outcome with the
`withdrawal.*` webhook events or by polling `retrieve()`.

You may hold **multiple wallets per currency**. Omit `walletId` and the payout
debits the currency's default wallet (or any spendable wallet with balance);
pass `walletId` to target a specific one.

## Wallets

```php
$wallets = $pagnow->wallets->list();
// → [['id' => ..., 'currency' => 'BRL', 'isDefault' => true, 'balance' => ...], ...]

$pagnow->wallets->retrieve($wallets[0]['id']);
```

Balances are in the smallest currency unit (centavos). Wallet creation is
handled by PagNow (admin); the SDK is read-only here.

## Webhooks

```php
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_PAGNOW_SIGNATURE'] ?? '';

if (!$pagnow->webhooks->verify($raw, $sig, getenv('PAGNOW_WEBHOOK_SECRET'))) {
    http_response_code(401);
    exit;
}
$event = json_decode($raw, true);
// ...handle $event['newStatus'] ...
http_response_code(200);
```

## Errors

Every non-2xx response throws a typed exception carrying the API's stable
error envelope (`statusCode`, `message`, `requestId`, `fieldErrors`):

| HTTP | Class | Use |
|---|---|---|
| 400 / 422 | `Pagnow\Exceptions\PagNowValidationException` | inspect `->fieldErrors` |
| 401 / 403 | `Pagnow\Exceptions\PagNowAuthException` | check the apikey / tenant status |
| 409 | `Pagnow\Exceptions\PagNowConflictException` | idempotencyKey reused with a different body |
| 5xx | `Pagnow\Exceptions\PagNowException` | retried automatically (3 attempts, jittered backoff) |
| transport | `Pagnow\Exceptions\PagNowNetworkException` | timeout / connection failure |

```php
use Pagnow\Exceptions\PagNowValidationException;

try {
    $pagnow->payments->create([...]);
} catch (PagNowValidationException $e) {
    foreach ($e->fieldErrors as $field => $reasons) {
        echo "$field: " . implode(', ', $reasons) . "\n";
    }
    echo "requestId: {$e->requestId}\n"; // quote this to support
}
```

## Defaults

- 30s per-request timeout (`timeoutMs`)
- 3 retries on 5xx / network errors with full-jitter exponential backoff; 4xx never retried
- Amounts are in the smallest currency unit (cents/centavos)

## Prefer raw HTTP?

The API is plain REST + JSON — implement directly with Guzzle/cURL using the
in-dashboard docs (Integração → Documentação) and the OpenAPI reference at
`https://v2.pagnow.com/docs/payments/reference`.

## License

MIT
