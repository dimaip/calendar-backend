<?php
require_once('init.php');
include __DIR__ . '/day.php';

exec('rm -f ' . dirname(__FILE__) . '/Data/cache_*');
exec('rm -f ' . dirname(__FILE__) . '/Data/cache/*');

getPerehod();
getNeperehod();

echo "Caches cleared";
