<?php

require_once 'vendor/autoload.php';

if (strpos($_SERVER['HTTP_HOST'], 'localhost:') !== 0) {
  ini_set('display_errors', 0);
  Sentry\init(['dsn' => 'https://e5296954a22242bc85d59b9a36559c44@sentry.io/3629452']);
}
