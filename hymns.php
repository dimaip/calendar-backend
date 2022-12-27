<?php
include __DIR__ . '/day.php';

function hymns() {
    $parsedown = new Parsedown();

    $rows = array_merge(
        getPerehod(), 
        getNeperehod()
    );
    
    $troparions = [];
    
    foreach($rows as $row) {
        if (!isset($row['Заглавие тропарион'])) {
            continue;
        }
        $id = md5($row['Заглавие тропарион']);
        $langMap = [
            'Рус' => 'ru',
            'Цся' => 'csj'
        ];
        $lang = $langMap[$row['Язык']];
        $bodytext = styleHtml($parsedown->text(($row['Тропари'] ?? '')) . $parsedown->text(($row['Кондаки'] ?? '')));
        if (isset($troparions[$id]["bodytext"]) && !isset($troparions[$id]["bodytext"]["$lang"])) {
            $troparions[$id]["bodytext"]["$lang"] = $bodytext;
        } else {
            $troparions[$id] = [
                "id" => $id,
                "title" => $row['Заглавие тропарион'],
                "bodytext" => [
                    "$lang" => $bodytext
                ],
            ];
        }
    }
    return array_values($troparions);
}

?>