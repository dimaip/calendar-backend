<?php
require_once('init.php');
include __DIR__ . '/day.php';

exec('rm -f ' . dirname(__FILE__) . '/Data/cache_*');
exec('rm -f ' . dirname(__FILE__) . '/Data/cache/*');

// Set global lock so day.php returns empty fast during warming
$globalLock = __DIR__ . '/Data/cache/airtable_global.lock';
@file_put_contents($globalLock, (string)time());

// Define warming mode and 30s deadline
define('AIRTABLE_WARMING', true);
define('AIRTABLE_DEADLINE', microtime(true) + 30.0);

// Warm required tables; function calls are resumable via tmp/cursor files
getPerehod();
getNeperehod();

// If all caches are fully populated, remove global lock; otherwise leave it for next run
function is_cache_complete()
{
    $required = [
        // Keys should match the URLs that getAirtable builds for the sets we use
        md5('https://api.airtable.com/v0/app9lgHrH4aDmn9IO/' . urlencode('Переходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/app1fn7GFDSwVrrt3/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/app2EOfdT7MF0CHkv/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appWJJXEUjVHOiHZB/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appaU3RHAHFfAGOiU/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appFCqIS9Fd69qatx/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appp9Lr7kOrNHAdkj/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appv2WDra6MYIJ8d8/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appKxcdLuiWPqcA4K/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appu454eFmvMCPd0B/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appkM6kjC92rWqdtq/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/appldjhytU1iITQl3/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
        md5('https://api.airtable.com/v0/app0Y6GpYy1JRQuvc/' . urlencode('Непереходящие') . '?view=Grid%20view&maxRecords=3000'),
    ];
    $base = __DIR__ . '/Data/cache/';
    foreach ($required as $hash) {
        $path = $base . $hash;
        if (!file_exists($path)) return false;
        $content = @file_get_contents($path);
        if ($content === 'lock') return false;
        $data = @json_decode($content, true);
        if (!is_array($data)) return false;
    }
    return true;
}

if (is_cache_complete()) {
    @unlink($globalLock);
    echo "Caches warmed; lock released";
} else {
    echo "Warming in progress; re-run to continue";
}
