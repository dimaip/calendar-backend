<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: *");
header("Access-Control-Allow-Methods: *");
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  // No further action needed for OPTIONS request
  exit(0);
}

try {
  if (preg_match('/^\/clear-cache/', $_SERVER["REQUEST_URI"])) {
    include_once __DIR__ . '/clearCache.php';
  }

  if (preg_match('/^\/app/', $_SERVER["REQUEST_URI"])) {
    include_once __DIR__ . '/app.php';
    exit();
  }

  if (preg_match('/^\/hymns/', $_SERVER["REQUEST_URI"])) {
    include_once __DIR__ . '/hymns.php';
    echo json_encode(hymns(), JSON_PRETTY_PRINT);
    exit();
  }

  if (preg_match('/^\/reading\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/bible.php';
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
    exit();
  }

  if (preg_match('/^\/readings\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/day.php';

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
    exit();
  }

  if (preg_match('/^\/parts\/(.+)\/(.*)/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/day.php';

    $day = new Day;
    $date = $matches[1] ?? null;
    $lang = $matches[2] ?? 'ru';
    $data = $day->parts($date, $lang);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
  }

  if (preg_match('/^\/day\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/day.php';

    $day = new Day;
    $date = $matches[1] ?? null;
    $data = $day->run($date);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
  }

  // $preferencesKey = 'PREFERENCES';

  if (preg_match('/^\/getSetting\/(.+)/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/userMetadata.php';

    $key = $matches[1];

    // $currentValue = getField($key) ?? [];
    // $value = $currentValue[$key] ?? null;

    echo json_encode(getField($key), JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
  }

  if (preg_match('/^\/getSettings/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/userMetadata.php';

    $currentValue = getFields();

    echo json_encode($currentValue, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
  }

  // if (preg_match('/^\/setSettings/', $_SERVER["REQUEST_URI"], $matches)) {
  //   include_once __DIR__ . '/userMetadata.php';

  //   $value = json_decode($_POST["value"]);

  //   setField($preferencesKey, $value);

  //   echo json_encode(['success' => true], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
  //   exit();
  // }

  if (preg_match('/^\/setSetting/', $_SERVER["REQUEST_URI"], $matches)) {
    include_once __DIR__ . '/userMetadata.php';

    $bodyJson = file_get_contents('php://input');

    // Decode the JSON payload into a PHP associative array
    $data = json_decode($bodyJson, true);

    $key = $data["key"];
    $value = $data["value"];

    // $currentValue = getField($preferencesKey) ?? [];

    // $mergedValue = array_merge((array)($currentValue ?? []), [$key => $value]);

    setField($key, json_encode($value, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));

    echo json_encode(['success' => true], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
  }
} catch (Exception $e) {
  error_log($e->getMessage());
  http_response_code(500);
  return [
    "errorCode" => "generic_error",
    "errorMessage" => $e->getMessage()
  ];
}
