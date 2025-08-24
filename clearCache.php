<?php
require_once('init.php');
include __DIR__ . '/day.php';
include __DIR__ . '/airtable_config.php';

exec('rm -f ' . dirname(__FILE__) . '/Data/cache_*');

// Create global lock so day.php returns [] while warming
$globalLock = __DIR__ . '/Data/cache/airtable_global.lock';
if (file_exists($globalLock)) {
    echo "Warming already in progress";
    return;
}
@file_put_contents($globalLock, (string)time());

// Spawn background PHP worker (detached) to warm caches without web timeouts
$cmd = 'nohup /usr/bin/env php ' . escapeshellarg(__DIR__ . '/warmAirtableCache.php') . ' > /dev/null 2>&1 &';
exec($cmd);

echo "Warming started in background";
