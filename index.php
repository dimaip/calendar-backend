<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

if (preg_match('/^\/clear-cache/', $_SERVER["REQUEST_URI"])) {
  include __DIR__ . '/clearCache.php';
}

if (preg_match('/^\/reading\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/bible.php';
  list($zachalo, $translation) = explode('&translation=', urldecode($matches[1]));
  if ($translation === 'default') {
    $translation = null;
  }
  if (!$translation) {
    $translation = null;
  }
  $bible = new Bible;
  $data = $bible->run($zachalo, $translation);
  echo json_encode($data, JSON_PRETTY_PRINT);
}

if (preg_match('/^\/readings\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/day.php';

  $day = new Day;
  $date = $matches[1] ?? null;
  $data = $day->run($date);
  $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($data['readings']));
  $result = [];
  foreach ($it as $complexVerse) {
    $bible = new Bible;
    if ($complexVerse) {
      $simpleVerses = explode('~', $complexVerse);
      foreach ($simpleVerses as $simpleVerse) {
        $result[$simpleVerse] = $bible->run($simpleVerse, null);
      }
    }
  }
  echo json_encode($result, JSON_PRETTY_PRINT);
}

if (preg_match('/^\/day\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/day.php';

  $day = new Day;
  $date = $matches[1] ?? null;
  $data = $day->run($date);
  echo json_encode($data, JSON_PRETTY_PRINT);
}
