<?php

// Ignore blank spreadsheet rows, but never accept an empty table or an HTML error page.
function parseCachedCsv($content)
{
    if (!is_string($content) || trim($content) === '' || preg_match('/^\s*</', $content)) {
        return null;
    }
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream)) !== false) {
        if (trim($row[0] ?? '') === '') {
            continue;
        }
        if (count($row) < 2) {
            fclose($stream);
            return null;
        }
        $rows[] = $row;
    }
    fclose($stream);
    return $rows ?: null;
}

// Readers see either the previous complete file or the new validated file.
function loadCsvCache($filename, $url, $refresh = false, $download = null)
{
    $cached = parseCachedCsv(@file_get_contents($filename));
    if ($cached && !$refresh) {
        return $cached;
    }
    $lock = fopen($filename . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock CSV cache: ' . $filename);
    }
    $temporary = null;
    try {
        $cached = parseCachedCsv(@file_get_contents($filename));
        if ($cached && !$refresh) {
            return $cached;
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $content = $download
                ? $download($url)
                : @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
            $rows = parseCachedCsv($content);
            if (!$rows) {
                continue;
            }
            $temporary = tempnam(dirname($filename), '.csv-');
            if ($temporary === false || file_put_contents($temporary, $content) !== strlen($content)
                || !chmod($temporary, 0644) || !rename($temporary, $filename)) {
                throw new RuntimeException('Unable to write CSV cache: ' . $filename);
            }
            $temporary = null;
            return $rows;
        }
        if ($cached) {
            error_log('CSV refresh failed; keeping previous data: ' . $filename);
            return $cached;
        }
        throw new RuntimeException('Unable to load valid CSV data: ' . $filename);
    } finally {
        if ($temporary && file_exists($temporary)) {
            unlink($temporary);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
