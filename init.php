<?php

require_once 'vendor/autoload.php';

if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_X_FORWARDED_HOST'], 'd.psmb.ru') === 0) {
  ini_set('display_errors', 0);
  Sentry\init(['dsn' => 'https://e5296954a22242bc85d59b9a36559c44@sentry.io/3629452']);
}
