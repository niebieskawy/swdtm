<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim($q) : '';

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = $_GET['limit'] ?? 6;
$limit = is_numeric($limit) ? (int)$limit : 6;
if ($limit < 1) $limit = 1;
if ($limit > 8) $limit = 8;

$photonUrl = 'https://photon.komoot.io/api';
$url = $photonUrl . '?q=' . urlencode($q) . '&limit=' . urlencode((string)$limit);

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'SWDTM Tablet Address Suggest'
    ]
]);

try {
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania sugestii adresu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $features = $data['features'] ?? [];
    if (!is_array($features)) $features = [];

    $out = [];
    foreach ($features as $f) {
        if (!is_array($f)) continue;
        $p = $f['properties'] ?? null;
        $g = $f['geometry'] ?? null;
        if (!is_array($p) || !is_array($g)) continue;

        $coords = $g['coordinates'] ?? null;
        if (!is_array($coords) || count($coords) < 2) continue;

        $city = (string)($p['city'] ?? $p['municipality'] ?? $p['county'] ?? '');
        $postcode = (string)($p['postcode'] ?? '');
        $street = (string)($p['street'] ?? $p['road'] ?? '');
        $number = (string)($p['housenumber'] ?? '');
        $name = (string)($p['name'] ?? '');

        $parts = [];
        $streetLine = trim(trim($street) . ' ' . trim($number));
        if ($streetLine !== '') $parts[] = $streetLine;
        if (trim($name) !== '' && mb_stripos($streetLine, $name) === false) {
            if ($streetLine === '') $parts[] = trim($name);
        }
        $cityLine = trim(trim($postcode) . ' ' . trim($city));
        if ($cityLine !== '') $parts[] = $cityLine;

        $label = trim(implode(', ', $parts));
        if ($label === '') continue;

        $out[] = [
            'label' => $label,
            'city' => $city,
            'postcode' => $postcode,
            'street' => $street,
            'number' => $number,
            'lat' => (float)$coords[1],
            'lon' => (float)$coords[0],
        ];

        if (count($out) >= $limit) break;
    }

    echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][tablet_address_suggest] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania sugestii adresu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
