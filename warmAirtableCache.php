<?php
require_once __DIR__ . '/init.php';
include __DIR__ . '/day.php';

ignore_user_abort(true);
@set_time_limit(0);
define('AIRTABLE_WARMING', true);

// Warm caches with robust backoff and rate limit (handled in day.php)
getPerehod();
getNeperehod();

// Release global lock
@unlink(__DIR__ . '/Data/cache/airtable_global.lock');

