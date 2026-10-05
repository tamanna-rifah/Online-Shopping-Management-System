<?php
declare(strict_types=1);

require_once __DIR__ . '/sslcommerz_config.php';

function sslcommerz_api_url(): string
{
    $config = sslcommerz_config();
    return $config['sandbox']
        ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
        : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
}

function sslcommerz_validation_url(): string
{
    $config = sslcommerz_config();
    return $config['sandbox']
        ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
        : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
}

function sslcommerz_init_payment(array $payload): array
{
    $config = sslcommerz_config();
    $payload['store_id'] = $config['store_id'];
    $payload['store_passwd'] = $config['store_passwd'];

    $ch = curl_init(sslcommerz_api_url());
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new RuntimeException('SSLCommerz init failed: ' . ($curlError !== '' ? $curlError : 'unexpected response'));
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Unexpected SSLCommerz init response.');
    }

    return $decoded;
}

function sslcommerz_validate_payment(string $valId): array
{
    $config = sslcommerz_config();
    $query = http_build_query([
        'val_id' => $valId,
        'store_id' => $config['store_id'],
        'store_passwd' => $config['store_passwd'],
        'format' => 'json',
    ]);

    $ch = curl_init(sslcommerz_validation_url() . '?' . $query);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new RuntimeException('SSLCommerz validation failed: ' . ($curlError !== '' ? $curlError : 'unexpected response'));
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Unexpected SSLCommerz validation response.');
    }

    return $decoded;
}
