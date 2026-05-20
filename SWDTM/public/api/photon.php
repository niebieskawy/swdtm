<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/photon_error.log');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? '';
$city = $_GET['city'] ?? '';
$street = $_GET['street'] ?? '';

$query = trim($query);
$type = trim($type);
$city = trim($city);
$street = trim($street);

function norm_str($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    $s = mb_strtolower($s, 'UTF-8');
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if (is_string($t) && $t !== '') $s = $t;
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = trim($s);
    return $s;
}

function props_city($properties) {
    if (!is_array($properties)) return '';
    return (string)($properties['city'] ?? $properties['town'] ?? $properties['village'] ?? $properties['municipality'] ?? '');
}

function props_street($properties) {
    if (!is_array($properties)) return '';
    return (string)($properties['street'] ?? $properties['road'] ?? $properties['pedestrian'] ?? $properties['footway'] ?? $properties['name'] ?? '');
}

function city_matches($properties, $wantedCity) {
    $w = norm_str($wantedCity);
    if ($w === '') return true;
    $c = norm_str(props_city($properties));
    if ($c === '') return false;
    return $c === $w;
}

function street_matches($properties, $wantedStreet) {
    $w = norm_str($wantedStreet);
    if ($w === '') return true;
    $s = norm_str(props_street($properties));
    if ($s === '') return false;
    return $s === $w;
}

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$photonUrl = 'https://photon.komoot.io/api';

$params = [];
switch ($type) {
    case 'city':
        // Wyszukiwanie miast
        $params['q'] = $query;
        break;
    case 'street':
        // Wyszukiwanie ulic - wymagaj miasta
        $fullQuery = $query;
        if ($city) {
            $fullQuery .= ', ' . $city;
        } else {
            echo json_encode([]);
            exit;
        }
        $params['q'] = $fullQuery;
        break;
    case 'number':
        // Wyszukiwanie numerów - wymagaj miasta i ulicy
        if (!$city || !$street) {
            echo json_encode([]);
            exit;
        }
        $fullQuery = $query . ' ' . $street . ', ' . $city;
        $params['q'] = $fullQuery;
        break;
    default:
        $params['q'] = $query;
        break;
}

$urlParts = [];
foreach ($params as $key => $value) {
    $urlParts[] = urlencode($key) . '=' . urlencode($value);
}
$url = $photonUrl . '?' . implode('&', $urlParts);

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'SWDTM Photon API Client'
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        error_log('file_get_contents failed: ' . ($error['message'] ?? 'Unknown error'));
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch data from Photon API', 'details' => $error['message'] ?? 'Unknown error']);
        exit;
    }
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Exception occurred', 'details' => $e->getMessage()]);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data)) {
    echo json_encode([]);
    exit;
}

$features = $data['features'] ?? [];

if (!is_array($features)) {
    echo json_encode([]);
    exit;
}

$result = [];
$seenNames = [];
foreach ($features as $feature) {
    if (!is_array($feature)) continue;
    
    $properties = $feature['properties'] ?? [];
    $geometry = $feature['geometry'] ?? [];
    
    if (!is_array($properties) || !is_array($geometry)) continue;

    if (($type === 'street' || $type === 'number') && $city !== '' && !city_matches($properties, $city)) {
        continue;
    }

    if ($type === 'number') {
        if ($street !== '' && !street_matches($properties, $street)) {
            continue;
        }
        $hn = trim((string)($properties['housenumber'] ?? ''));
        if ($hn === '') {
            continue;
        }
    }
    
    $coords = $geometry['coordinates'] ?? [];
    if (!is_array($coords) || count($coords) < 2) continue;
    
    // Filtracja dla miast - tylko place:city, place:town, place:village
    if ($type === 'city') {
        $osmTags = $properties['osm_tags'] ?? [];
        $osmValue = $properties['osm_value'] ?? '';
        $osmKey = $properties['osm_key'] ?? '';
        
        if (!($osmKey === 'place' && in_array($osmValue, ['city', 'town', 'village', 'municipality'], true))) {
            continue;
        }
    }
    
    // Filtracja dla ulic - tylko highway
    if ($type === 'street') {
        $osmKey = $properties['osm_key'] ?? '';
        if ($osmKey !== 'highway') {
            continue;
        }
    }

    $name = $properties['name'] ?? '';
    $housenumber = $properties['housenumber'] ?? '';
    
    // Usuwanie duplikatów po nazwie dla ulic i miast
    if ($type === 'street' || $type === 'city') {
        if (in_array($name, $seenNames, true)) {
            continue;
        }
        $seenNames[] = $name;
    }

    if ($type === 'number') {
        $hnKey = trim((string)$housenumber);
        if ($hnKey === '') {
            continue;
        }
        if (in_array($hnKey, $seenNames, true)) {
            continue;
        }
        $seenNames[] = $hnKey;
    }
    
    $item = [
        'type' => 'Feature',
        'properties' => [
            'name' => $name,
            'city' => $properties['city'] ?? $properties['municipality'] ?? $properties['county'] ?? '',
            'postcode' => $properties['postcode'] ?? '',
            'street' => $properties['street'] ?? $properties['road'] ?? '',
            'housenumber' => $properties['housenumber'] ?? '',
            'country' => $properties['country'] ?? '',
            'state' => $properties['state'] ?? '',
        ],
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [$coords[0], $coords[1]]
        ]
    ];
    
    $result[] = $item;
}

echo json_encode(array_slice($result, 0, 10));
