<?php

require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists('Data/cache')) {
  mkdir('Data/cache', 0755, true);
}

function getNotification()
{
  $url = "https://api.airtable.com/v0/appCjyqSNOZqQXXbq/%D0%A3%D0%B2%D0%B5%D0%B4%D0%BE%D0%BC%D0%BB%D0%B5%D0%BD%D0%B8%D1%8F?maxRecords=3&view=Grid%20view";
  $filename = 'Data/cache/' . md5($url);

  if (file_exists($filename) && false) {
    $content = file_get_contents($filename);
    $record = json_decode($content, true);
  } else {
    // Go through pagination and accumulate all records
    $content = file_get_contents($url, false, stream_context_create([
      'http' => [
        'method' => "GET",
        // This is the read-only key, it's safe to expose it publicly
        'header' => "Authorization: Bearer keygUv0FLzqXCLjvt\r\n"
      ]
    ]));
    $data = json_decode($content, true);
    if (isset($data['records'][0]['fields']['id'])) {
      $fields = $data['records'][0]['fields'];
      $record = [
        'id' => $fields['id'],
        'title' => $fields['Заголовок'] ?? '',
        'subtitle' => $fields['Подзаголовок'] ?? '',
        'buttonText' => $fields['Текст кнопки'] ?? '',
        'backgroundColour' => $fields['Цвет фона'] ?? '',
        'buttonColour' => $fields['Цвет кнопки'] ?? '',
        'activeSince' => $fields['Активен с'] ?? '',
        'activeTill' => $fields['Активен до'] ?? ''
      ];
    } else {
      $record = null;
    }

    file_put_contents($filename, json_encode($record));
  }
  return $record;
}

$notification = getNotification();

header('Content-Type: application/json');
echo json_encode([
  'notification' => $notification
]);
