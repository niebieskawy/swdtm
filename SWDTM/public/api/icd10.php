<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/icd10_error.log');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim($q) : '';

if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$cacheFile = __DIR__ . '/icd10_pl_cache.json';
$sourceUrl = 'https://raw.githubusercontent.com/basiekjusz/icd-pl/main/json/icd10_pl.json';

$needsRefresh = true;
if (is_file($cacheFile)) {
    $age = time() - (int)@filemtime($cacheFile);
    if ($age >= 0 && $age < 30 * 24 * 3600) {
        $needsRefresh = false;
    }
}

if ($needsRefresh) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'SWDTM ICD10 Client',
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    try {
        $raw = file_get_contents($sourceUrl, false, $ctx);
        if ($raw !== false && $raw !== '') {
            @file_put_contents($cacheFile, $raw);
        }
    } catch (Throwable $e) {
        error_log('ICD10 download exception: ' . $e->getMessage());
    }
}

$rawLocal = @file_get_contents($cacheFile);
if (!is_string($rawLocal) || $rawLocal === '') {
    echo json_encode([]);
    exit;
}

$dict = json_decode($rawLocal, true);
if (!is_array($dict)) {
    echo json_encode([]);
    exit;
}

function swdtm_norm(string $s): string
{
    $s = mb_strtolower($s);
    $map = [
        'ą' => 'a',
        'ć' => 'c',
        'ę' => 'e',
        'ł' => 'l',
        'ń' => 'n',
        'ó' => 'o',
        'ś' => 's',
        'ź' => 'z',
        'ż' => 'z',
    ];
    return strtr($s, $map);
}

$needle = swdtm_norm($q);

$candidates = [];
foreach ($dict as $row) {
    if (!is_array($row)) {
        continue;
    }

    $code = $row['code'] ?? '';
    $name = $row['name'] ?? '';

    if (!is_string($code) || !is_string($name)) {
        continue;
    }

    $code = trim($code);
    $name = trim($name);
    if ($code === '' || $name === '') {
        continue;
    }

    $codeN = swdtm_norm($code);
    $nameN = swdtm_norm($name);

    $score = null;
    if (str_starts_with($codeN, $needle)) {
        $score = 0;
    } elseif (str_contains($codeN, $needle)) {
        $score = 1;
    } elseif (str_starts_with($nameN, $needle)) {
        $score = 2;
    } elseif (str_contains($nameN, $needle)) {
        $score = 3;
    }

    if ($score === null) {
        continue;
    }

    $candidates[] = [
        'code' => $code,
        'name' => $name,
        '_score' => $score,
        '_code_len' => mb_strlen($code),
    ];
}

usort($candidates, function (array $a, array $b): int {
    if ($a['_score'] !== $b['_score']) {
        return $a['_score'] <=> $b['_score'];
    }
    if ($a['_code_len'] !== $b['_code_len']) {
        return $a['_code_len'] <=> $b['_code_len'];
    }
    return strcmp($a['code'], $b['code']);
});

$out = [];
foreach (array_slice($candidates, 0, 20) as $it) {
    $out[] = ['code' => $it['code'], 'name' => $it['name']];
}

echo json_encode($out);
