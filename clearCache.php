<?php
require_once('init.php');

exec('rm -f ' . dirname(__FILE__) . '/Data/cache_*');
exec('rm -f ' . dirname(__FILE__) . '/Data/cache/*');
echo "Caches cleared";
