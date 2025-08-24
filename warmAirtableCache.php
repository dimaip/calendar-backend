<?php
require_once __DIR__ . '/init.php';
include __DIR__ . '/day.php';
include __DIR__ . '/airtable_config.php';

ignore_user_abort(true);
@set_time_limit(0);
// In this script we perform Airtable fetching and write caches directly

function airtable_simple_rate_limit($minIntervalSeconds = 0.25)
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

function warm_airtable_table($tableId, $tableName)
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
            airtable_simple_rate_limit();
            $content = @file_get_contents($requestUrl, false, $context);
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

// Warm all required tables
$allOk = true;
foreach (airtable_sources() as $src) {
    $allOk = warm_airtable_table($src['tableId'], $src['tableName']) && $allOk;
}

// Release global lock
@unlink(__DIR__ . '/Data/cache/airtable_global.lock');
