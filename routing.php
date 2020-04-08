<?php

# .htaccess denies a bunch of directories
if (preg_match('/^\/(config|content|content-sample|lib|vendor)\//', $_SERVER['REQUEST_URI'])) {
  # Return 404
  $_SERVER['QUERY_STRING'] = '404';
} else {
  # .htaccess allows direct access to any files
  # (as long as they're not in the directories denied above)
  if (file_exists(__DIR__ . '/' . $_SERVER['REQUEST_URI'])) {
    return false; // serve the requested resource as-is.
  }
  $_SERVER['PICO_URL_REWRITING'] = 1;
  $_SERVER['QUERY_STRING'] = urlencode($_SERVER['REQUEST_URI']);
}
include __DIR__ . '/index.php';
