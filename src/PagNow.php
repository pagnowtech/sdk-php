<?php

declare(strict_types=1);

namespace Pagnow;

use Pagnow\Exceptions\PagNowAuthException;
use Pagnow\Exceptions\PagNowConflictException;
use Pagnow\Exceptions\PagNowException;
use Pagnow\Exceptions\PagNowNetworkException;
use Pagnow\Exceptions\PagNowValidationException;

/**
 * Official PHP client for the PagNow payments API.
 *
 *   $pagnow = new \Pagnow\PagNow(['apiKey' => getenv('PAGNOW_API_KEY')]);
 *   $charge = $pagnow->payments->create([...]);
 *
 * The apikey identifies your tenant — no tenantId needed. Zero runtime
 * dependencies (native cURL), mirroring @pagnow/sdk-node.
 */
final class PagNow
{
    public readonly Payments $payments;
    public readonly Payouts $payouts;
    public readonly Webhooks $webhooks;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeoutMs;
    private readonly int $maxRetries;

    /**
     * @param array{apiKey:string, baseUrl?:string, timeoutMs?:int, maxRetries?:int} $opts
     */
    public function __construct(array $opts)
    {
        if (empty($opts['apiKey'])) {
            throw new \InvalidArgumentException('PagNow: apiKey is required');
        }
        $this->apiKey = $opts['apiKey'];
        $this->baseUrl = rtrim($opts['baseUrl'] ?? 'https://v2.pagnow.com', '/');
        $this->timeoutMs = $opts['timeoutMs'] ?? 30000;
        $this->maxRetries = $opts['maxRetries'] ?? 3;
        $this->payments = new Payments($this);
        $this->payouts = new Payouts($this);
        $this->webhooks = new Webhooks();
    }

    /**
     * Internal — used by resource classes. Performs the HTTP call with
     * retries on 5xx/network errors and maps non-2xx into typed exceptions.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|list<mixed>|null
     */
    public function request(string $method, string $path, ?array $body = null): array|null
    {
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        $headers = ['apikey: ' . $this->apiKey, 'Accept: application/json'];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
        }

        $attempt = 0;
        $lastError = null;
        while ($attempt < $this->maxRetries) {
            $attempt++;
            $ch = curl_init($url);
            // Build options explicitly — do NOT use array spread here: the keys
            // are integer CURLOPT_* constants and `...` re-indexes integer keys.
            $curlOpts = [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            ];
            if ($payload !== null) {
                $curlOpts[CURLOPT_POSTFIELDS] = $payload;
            }
            curl_setopt_array($ch, $curlOpts);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $lastError = new PagNowNetworkException('PagNow request failed: ' . curl_error($ch));
                curl_close($ch);
            } else {
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $rawHeaders = substr((string) $raw, 0, $headerSize);
                $rawBody = substr((string) $raw, $headerSize);
                curl_close($ch);

                $parsed = $rawBody !== '' ? json_decode($rawBody, true) : null;
                $requestId = self::headerValue($rawHeaders, 'x-request-id');

                if ($status >= 200 && $status < 300) {
                    return is_array($parsed) ? $parsed : null;
                }
                if ($status >= 400 && $status < 500) {
                    throw $this->classify4xx($status, is_array($parsed) ? $parsed : null, $requestId);
                }
                // 5xx — retryable
                $lastError = new PagNowException(
                    "PagNow {$method} {$path} failed with {$status}",
                    $status,
                    $requestId,
                    [],
                    is_array($parsed) ? $parsed : null,
                );
            }

            // Full-jitter exponential backoff (cap 8s).
            $baseMs = min(1000 * (2 ** ($attempt - 1)), 8000);
            usleep(random_int(0, $baseMs) * 1000);
        }

        throw $lastError ?? new PagNowNetworkException('PagNow request failed after retries');
    }

    /** @param array<string, mixed>|null $body */
    private function classify4xx(int $status, ?array $body, ?string $requestId): PagNowException
    {
        $message = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : "PagNow {$status}";
        /** @var array<string, list<string>> $fieldErrors */
        $fieldErrors = is_array($body) && is_array($body['fieldErrors'] ?? null) ? $body['fieldErrors'] : [];

        return match (true) {
            $status === 401 || $status === 403 => new PagNowAuthException($message, $status, $requestId, [], $body),
            $status === 409 => new PagNowConflictException($message, $status, $requestId, [], $body),
            $status === 400 || $status === 422 => new PagNowValidationException($message, $status, $requestId, $fieldErrors, $body),
            default => new PagNowException($message, $status, $requestId, [], $body),
        };
    }

    private static function headerValue(string $rawHeaders, string $name): ?string
    {
        foreach (preg_split('/\r?\n/', $rawHeaders) ?: [] as $line) {
            if (stripos($line, $name . ':') === 0) {
                return trim(substr($line, strlen($name) + 1));
            }
        }
        return null;
    }
}
