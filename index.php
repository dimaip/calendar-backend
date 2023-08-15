<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

if (preg_match('/^\/clear-cache/', $_SERVER["REQUEST_URI"])) {
  include __DIR__ . '/clearCache.php';
}

if (preg_match('/^\/app/', $_SERVER["REQUEST_URI"])) {
  include __DIR__ . '/app.php';
}

if (preg_match('/^\/hymns/', $_SERVER["REQUEST_URI"])) {
  include __DIR__ . '/hymns.php';
  echo json_encode(hymns(), JSON_PRETTY_PRINT);
}

if (preg_match('/^\/reading\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/bible.php';
  $explodedUrl = explode('&translation=', urldecode($matches[1]));
  $zachalo = $explodedUrl[0];
  if (isset($explodedUrl[1])) {
    $translation = $explodedUrl[1];
  } else {
    $translation = null;
  }
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
  $readings = $data['bReadings'] ? array_merge($data['readings'], $data['bReadings']) : $data['readings'];
  $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($readings));
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

if (preg_match('/^\/parts\/(.+)\/(.*)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/day.php';

  $day = new Day;
  $date = $matches[1] ?? null;
  $lang = $matches[2] ?? 'ru';
  $data = $day->parts($date, $lang);
  echo json_encode($data, JSON_PRETTY_PRINT);
}

if (preg_match('/^\/day\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/day.php';

  $day = new Day;
  $date = $matches[1] ?? null;
  $data = $day->run($date);
  echo json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
}

$preferencesKey = 'PREFERENCES';

if (preg_match('/^\/getSetting\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/userMetadata.php';

  $key = $matches[1] ?? null;

  $currentValue = getField($preferencesKey) ?? [];
  $value = $currentValue[$key] ?? null;

  echo json_encode($value, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
}

if (preg_match('/^\/getSettings/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/userMetadata.php';

  $currentValue = getField($preferencesKey) ?? null;

  echo json_encode($currentValue, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
}

if (preg_match('/^\/setSettings/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/userMetadata.php';

  $value = $_POST["value"];

  setField($preferencesKey, $value);

  echo json_encode(['success' => true], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
}

if (preg_match('/^\/setSetting/', $_SERVER["REQUEST_URI"], $matches)) {
  include __DIR__ . '/userMetadata.php';

  $key = $_POST["key"];
  $value = $_POST["value"];

  $currentValue = getField($preferencesKey) ?? [];

  $mergedValue = array_merge((array)($currentValue ?? []), [$key => $value]);

  setField($preferencesKey, $mergedValue);

  echo json_encode(['success' => true], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
}
