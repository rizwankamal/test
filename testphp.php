<?php
/**
 * WooCommerce coupon fetch + filter
 *
 * Filters OUT:
 * - Expired coupons (date_expires/date_expires_gmt is in the past)
 * - Fully-used coupons (usage_limit is set AND usage_count >= usage_limit)
 */

declare(strict_types=1);

/**
 * Fetch JSON from a WooCommerce REST endpoint.
 *
 * Note: For production, prefer sending credentials via Authorization header.
 * This sample uses query parameters for simplicity.
 */
function wcFetchJson(string $url, string $consumerKey, string $consumerSecret): array
{
    $sep = (str_contains($url, '?')) ? '&' : '?';
    $urlWithAuth = $url . $sep . http_build_query([
        'consumer_key' => $consumerKey,
        'consumer_secret' => $consumerSecret,
    ]);

    $ch = curl_init($urlWithAuth);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL error: ' . $err);
    }

    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ' from WooCommerce: ' . $raw);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON from WooCommerce');
    }

    return $decoded;
}

function wcParseDate(?string $value, ?DateTimeZone $fallbackTz = null): ?DateTimeImmutable
{
    if ($value === null || $value === '') {
        return null;
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt;
    } catch (Throwable $e) {
        // Some stores may return values without timezone info; try again with fallback.
        if ($fallbackTz === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, $fallbackTz));
        } catch (Throwable $e2) {
            return null;
        }
    }
}

function wcIsExpired(array $coupon, ?DateTimeImmutable $nowUtc = null): bool
{
    $nowUtc = $nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

    // Prefer GMT field when present.
    $expiresGmt = wcParseDate($coupon['date_expires_gmt'] ?? null, new DateTimeZone('UTC'));
    if ($expiresGmt instanceof DateTimeImmutable) {
        return $expiresGmt < $nowUtc;
    }

    $expiresLocal = wcParseDate($coupon['date_expires'] ?? null, null);
    if ($expiresLocal instanceof DateTimeImmutable) {
        // Normalize to UTC for comparison.
        return $expiresLocal->setTimezone(new DateTimeZone('UTC')) < $nowUtc;
    }

    // No expiry date => not expired
    return false;
}

function wcIsFullyUsed(array $coupon): bool
{
    $usageLimit = $coupon['usage_limit'] ?? null;
    $usageCount = $coupon['usage_count'] ?? 0;

    if ($usageLimit === null) {
        return false; // unlimited
    }

    // Treat 0 / empty as unlimited in many Woo setups.
    if (!is_numeric($usageLimit) || (int)$usageLimit <= 0) {
        return false;
    }

    return (int)$usageCount >= (int)$usageLimit;
}

function wcFilterValidCoupons(array $coupons): array
{
    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return array_values(array_filter($coupons, function ($c) use ($nowUtc) {
        if (!is_array($c)) {
            return false;
        }
        if (wcIsExpired($c, $nowUtc)) {
            return false;
        }
        if (wcIsFullyUsed($c)) {
            return false;
        }
        return true;
    }));
}

// -----------------
// Example usage
// -----------------

$endpoint = 'https://www.besteverpads.com/wp-json/wc/v3/coupons/104670';

// TODO: put your real keys here (or load from env vars).
$consumerKey = getenv('WC_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('WC_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

try {
    $data = wcFetchJson($endpoint, $consumerKey, $consumerSecret);

    // Normalize single coupon vs list response.
    $coupons = array_is_list($data) ? $data : [$data];

    $valid = wcFilterValidCoupons($coupons);

    header('Content-Type: application/json');
    echo json_encode([
        'count_in' => count($coupons),
        'count_out' => count($valid),
        'coupons' => $valid,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
