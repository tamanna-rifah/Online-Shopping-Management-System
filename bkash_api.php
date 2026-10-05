<?php
declare(strict_types=1);

require_once __DIR__ . '/bkash_config.php';

function bkash_missing_config_fields(): array
{
    $config = bkash_config();
    $missing = [];

    foreach (['app_key', 'app_secret', 'username', 'password'] as $key) {
        $value = (string) ($config[$key] ?? '');
        if (
            $value === '' ||
            str_starts_with($value, 'YOUR_BKASH_') ||
            str_starts_with($value, 'PASTE_YOUR_BKASH_')
        ) {
            $missing[] = $key;
        }
    }

    return $missing;
}

function bkash_has_live_credentials(): bool
{
    return bkash_missing_config_fields() === [];
}

function bkash_validate_config(): void
{
    $missing = bkash_missing_config_fields();

    if ($missing) {
        throw new RuntimeException(
            'bKash sandbox credentials ekhono configure kora hoyni. `bkash_credentials.php` file open kore app_key, app_secret, username, password boshao. Missing: ' . implode(', ', $missing)
        );
    }
}

function bkash_demo_payment_id(int $orderId, int $paymentRowId): string
{
    return 'DEMO-PAY-' . $orderId . '-' . $paymentRowId;
}

function bkash_demo_trx_id(int $orderId, int $paymentRowId): string
{
    return 'DEMO-TRX-' . $orderId . '-' . $paymentRowId;
}

function bkash_api_request(string $method, string $path, array $payload = [], array $headers = []): array
{
    $config = bkash_config();
    $url = rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
    $curlHeaders = array_merge(
        [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        $headers
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

    if ($payload !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('bKash request failed: ' . $curlError);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Unexpected bKash response: ' . $body);
    }

    if ($httpCode >= 400) {
        $message = $decoded['statusMessage']
            ?? $decoded['message']
            ?? ('HTTP ' . $httpCode);
        throw new RuntimeException('bKash API error: ' . $message);
    }

    if (
        isset($decoded['statusCode']) &&
        !in_array((string) $decoded['statusCode'], ['0000', '000', '200', '206'], true)
    ) {
        $message = $decoded['statusMessage'] ?? 'Unknown bKash error';
        throw new RuntimeException('bKash API error: ' . $message);
    }

    return $decoded;
}

function bkash_grant_token(): string
{
    bkash_validate_config();
    $config = bkash_config();

    $response = bkash_api_request(
        'POST',
        'tokenized/checkout/token/grant',
        [
            'app_key' => $config['app_key'],
            'app_secret' => $config['app_secret'],
        ],
        [
            'username: ' . $config['username'],
            'password: ' . $config['password'],
        ]
    );

    $idToken = $response['id_token'] ?? $response['idToken'] ?? '';
    if ($idToken === '') {
        throw new RuntimeException('bKash token response did not include an id_token.');
    }

    return $idToken;
}

function bkash_create_payment(string $idToken, array $payload): array
{
    $config = bkash_config();

    return bkash_api_request(
        'POST',
        'tokenized/checkout/create',
        $payload,
        [
            'authorization: ' . $idToken,
            'x-app-key: ' . $config['app_key'],
        ]
    );
}

function bkash_execute_payment(string $idToken, string $paymentId): array
{
    $config = bkash_config();

    return bkash_api_request(
        'POST',
        'tokenized/checkout/execute',
        ['paymentID' => $paymentId],
        [
            'authorization: ' . $idToken,
            'x-app-key: ' . $config['app_key'],
        ]
    );
}

function bkash_query_payment(string $idToken, string $paymentId): array
{
    $config = bkash_config();

    return bkash_api_request(
        'POST',
        'tokenized/checkout/payment/status',
        ['paymentID' => $paymentId],
        [
            'authorization: ' . $idToken,
            'x-app-key: ' . $config['app_key'],
        ]
    );
}
