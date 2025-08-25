<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/airtable_config.php';

ignore_user_abort(true);
@set_time_limit(0);
// In this script we perform Airtable fetching and write caches directly

function airtableSimpleRateLimit($minIntervalSeconds = 0.25)
{
    static $lastStart = 0.0;
    $now = microtime(true);
    $sleepSeconds = ($lastStart + $minIntervalSeconds) - $now;
    if ($sleepSeconds > 0) {
        usleep((int)($sleepSeconds * 1000000));
        $now = microtime(true);
    }
    $lastStart = $now;
}

function warmAirtableTable($tableId, $tableName)
{
    $baseUrl = 'https://api.airtable.com/v0/';
    $url = $baseUrl . $tableId . '/' . urlencode($tableName) . '?view=Grid%20view&maxRecords=3000';
    $filename = __DIR__ . '/Data/cache/' . md5($url);
    $tmpFile = $filename . '.tmp';
    $cursorFile = $filename . '.cursor';
    $offset = null;
    $records = [];
    if (file_exists($tmpFile)) {
        $tmpContent = @file_get_contents($tmpFile);
        if ($tmpContent) {
            $records = json_decode($tmpContent, true) ?: [];
        }
    }
    if (file_exists($cursorFile)) {
        $saved = trim((string)@file_get_contents($cursorFile));
        $offset = $saved !== '' ? $saved : null;
    }
    do {
        $requestUrl = $url . ($offset ? '&offset=' . $offset : '');
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\nAuthorization: Bearer patVQ2ONx3NyvrTl8.d918549ba9b1caee42474af09fff67e68f49f5b81885ea9b0e6d748d29de788b\r\n",
            ],
        ]);
        $content = false;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            airtableSimpleRateLimit();
            $content = @file_get_contents($requestUrl, false, $context);
            if ($content !== false) {
                incrementAirtableCounter(1);
            }
            if ($content !== false) break;
            usleep(250000);
        }
        if ($content === false) {
            // Save progress and bail out; next run resumes
            @file_put_contents($tmpFile, json_encode($records));
            @file_put_contents($cursorFile, (string)($offset ?? ''));
            return false;
        }
        $data = json_decode($content, true);
        if (!isset($data['records'])) {
            @file_put_contents($tmpFile, json_encode($records));
            @file_put_contents($cursorFile, (string)($offset ?? ''));
            return false;
        }
        $newRecords = array_map(function ($record) {
            return $record['fields'];
        }, $data['records']);
        $records = array_merge($records, $newRecords);
        $offset = $data['offset'] ?? null;
        @file_put_contents($tmpFile, json_encode($records));
        @file_put_contents($cursorFile, (string)($offset ?? ''));
    } while ($offset);
    // Completed: write final cache and cleanup
    @file_put_contents($filename, json_encode($records));
    @unlink($tmpFile);
    @unlink($cursorFile);
    return true;
}

// Logging helper
$cacheDir = __DIR__ . '/Data/cache';
if (!file_exists($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$logFile = $cacheDir . '/warm.log';
$log = function ($message) use ($logFile) {
    @file_put_contents($logFile, date('c') . ' ' . $message . "\n", FILE_APPEND);
};

// Monthly Airtable request counter
function getAirtableCounterPath()
{
    return __DIR__ . '/Data/cache/airtable_requests.json';
}
function loadAirtableCounter()
{
    $path = getAirtableCounterPath();
    if (!file_exists($path)) {
        return ['yearMonth' => date('Y-m'), 'count' => 0];
    }
    $raw = @file_get_contents($path);
    $data = @json_decode($raw, true);
    if (!is_array($data)) {
        return ['yearMonth' => date('Y-m'), 'count' => 0];
    }
    if (!isset($data['yearMonth']) || !isset($data['count'])) {
        $data = ['yearMonth' => date('Y-m'), 'count' => 0];
    }
    return $data;
}
function saveAirtableCounter($data)
{
    @file_put_contents(getAirtableCounterPath(), json_encode($data));
}
function ensureCounterCurrentMonth(&$data)
{
    $currentYm = date('Y-m');
    if (!isset($data['yearMonth']) || $data['yearMonth'] !== $currentYm) {
        $data['yearMonth'] = $currentYm;
        $data['count'] = 0;
    }
}
function incrementAirtableCounter($by = 1)
{
    $data = loadAirtableCounter();
    ensureCounterCurrentMonth($data);
    $data['count'] = (int)$data['count'] + $by;
    saveAirtableCounter($data);
}

// Optional single-table warm via CLI args: --tableId=... --tableName=...
$args = getopt('', ['tableId::', 'tableName::']);
$singleTableId = isset($args['tableId']) ? (string)$args['tableId'] : null;
$singleTableName = isset($args['tableName']) ? (string)$args['tableName'] : null;

try {
    $allOk = true;
    if ($singleTableId && $singleTableName) {
        $log('Warm start single table: ' . $singleTableId . ' / ' . $singleTableName);
        $allOk = warmAirtableTable($singleTableId, $singleTableName) && $allOk;
    } else {
        $log('Warm start all tables');
        foreach (airtable_sources() as $src) {
            $log('Warming table: ' . $src['tableId'] . ' / ' . $src['tableName']);
            $allOk = warmAirtableTable($src['tableId'], $src['tableName']) && $allOk;
        }
    }
    $log('Warm finished, success=' . ($allOk ? 'true' : 'false'));
} catch (Throwable $e) {
    $log('Warm error: ' . $e->getMessage());
} finally {
    // Release global lock even if something failed
    @unlink(__DIR__ . '/Data/cache/airtable_global.lock');
}
