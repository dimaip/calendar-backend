<?php
require_once('init.php');
require_once('functions.php');
require_once('bible.php');

if (!file_exists('Data/cache')) {
    mkdir('Data/cache', 0755, true);
}

function styleHtml($text)
{
    return str_replace(
        ['<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>', '<p>', '</p>', '<strong>', '</strong>', '<blockquote>', '</blockquote>', '<code>', '</code>', '<del>', '</del>'],
        ['<h1 class="H1">', '</h1>', '<h2 class="H2">', '</h2>', '<h3 class="H3">', '</h3>', '<h4 class="H4">', '</h4>', '<p class="P">', '</p>', '<span class="Red">', '</span>', '<div class="Petit">', '</div>', '<span class="PetitInline">', '</span>', '<span class="Super">', '</span>'],
        $text
    );
}

// Ensure Airtable requests are rate-limited across parallel PHP processes.
// Airtable allows 5 requests/second; we schedule starts >= ~210ms apart globally.
function airtable_rate_limit_schedule($minIntervalSeconds = 0.21)
{
    $scheduleFile = 'Data/cache/airtable_rate_limit.schedule';
    $fp = fopen($scheduleFile, 'c+');
    if (!$fp) {
        // If we cannot open the schedule file, fall back to a conservative sleep.
        usleep((int)($minIntervalSeconds * 1000000));
        return;
    }
    // Acquire exclusive lock to coordinate between processes
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        usleep((int)($minIntervalSeconds * 1000000));
        return;
    }
    // Read last scheduled start time
    rewind($fp);
    $raw = stream_get_contents($fp);
    $lastScheduled = 0.0;
    if ($raw !== false) {
        $raw = trim($raw);
        if ($raw !== '') {
            $lastScheduled = (float)$raw;
        }
    }
    $now = microtime(true);
    $earliestStart = max($now, $lastScheduled + $minIntervalSeconds);
    // Write back the new scheduled time
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, sprintf('%.6F', $earliestStart));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    // Sleep until our scheduled slot
    $sleepSeconds = $earliestStart - $now;
    if ($sleepSeconds > 0) {
        usleep((int)($sleepSeconds * 1000000));
    }
}

function rate_limited_get_contents($url, $context = null)
{
    airtable_rate_limit_schedule();
    return file_get_contents($url, false, $context);
}

function getAirtable($tableId, $tableName)
{
    $url = "https://api.airtable.com/v0/" . $tableId . "/" . urlencode($tableName) . "?view=Grid%20view&maxRecords=3000";
    $filename = 'Data/cache/' . md5($url);

    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        if ($content == 'lock') {
            return [];
        }
        $records = json_decode($content, true);
    } else {
        file_put_contents($filename, 'lock');
        $offset = null;
        $records = [];
        // Go through pagination and accumulate all records
        do {
            $content = null;
            $retryCount = 0;
            while (!$content) {
                $requestUrl = $url . ($offset ? '&offset=' . $offset : '');
                $context = stream_context_create([
                    'http' => [
                        'method' => "GET",
                        // This is the read-only key, it's safe to expose it publicly
                        'header' => "Authorization: Bearer patVQ2ONx3NyvrTl8.d918549ba9b1caee42474af09fff67e68f49f5b81885ea9b0e6d748d29de788b\r\n"
                    ]
                ]);
                $content = rate_limited_get_contents($requestUrl, $context);
                if ($retryCount > 3) {
                    throw new Exception("Could not fetch $url");
                }
                $retryCount++;
            }
            $data = json_decode($content, true);
            $newRecords = array_map(function ($record) {
                return $record['fields'];
            }, $data['records']);
            $records = array_merge($records, $newRecords);
            $offset = $data['offset'] ?? null;
        } while ($offset);
        file_put_contents($filename, json_encode($records));
    }
    return $records;
}
function getPerehod()
{
    return getAirtable("app9lgHrH4aDmn9IO", "Переходящие");
}
function getNeperehod()
{
    return array_merge(
        getAirtable('app1fn7GFDSwVrrt3', 'Непереходящие'),
        getAirtable('app2EOfdT7MF0CHkv', 'Непереходящие'),
        getAirtable('appWJJXEUjVHOiHZB', 'Непереходящие'),
        getAirtable('appaU3RHAHFfAGOiU', 'Непереходящие'),
        getAirtable('appFCqIS9Fd69qatx', 'Непереходящие'),
        getAirtable('appp9Lr7kOrNHAdkj', 'Непереходящие'),
        getAirtable('appv2WDra6MYIJ8d8', 'Непереходящие'),
        getAirtable('appKxcdLuiWPqcA4K', 'Непереходящие'),
        getAirtable('appu454eFmvMCPd0B', 'Непереходящие'),
        getAirtable('appkM6kjC92rWqdtq', 'Непереходящие'),
        getAirtable('appldjhytU1iITQl3', 'Непереходящие'),
        getAirtable('app0Y6GpYy1JRQuvc', 'Непереходящие'),
    );
}

class Day
{
    protected $isDebug = false;
    protected $perehod;
    protected $neperehod;
    protected $bReadings;
    protected $sundayMatinsGospels;
    protected $zachala;
    protected $saints;
    protected $prazdnikTitle;
    protected $skipRjadovoe;
    protected $noLiturgy;
    protected $dayOfWeekNumber;
    protected $parsedown;

    function __construct()
    {
        $this->parsedown = new Parsedown();
    }

    protected $dayOfWeekNames = ['воскресение', 'понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу'];

    protected function getStaticData($datestamp)
    {
        $d = date('Y-m-d', $datestamp);
        $filename = 'Data/processed/' . $d;
        if (file_exists($filename)) {
            return json_decode(file_get_contents($filename), true);
        } else {
            return null;
        }
    }

    protected function normalizeDayOfWeek($dayOfWeekNumber)
    {
        if ($dayOfWeekNumber < 0) {
            return $dayOfWeekNumber + 7;
        }
        if ($dayOfWeekNumber > 6) {
            return $dayOfWeekNumber - 7;
        }
        return $dayOfWeekNumber;
    }

    protected function getDayAfter($date, $dayNumber = 1, $shTimes = 0, $noJumpIfSameDay = 0)
    {
        $day_stamp = strtotime($date);
        $currentDayNumber = date('w', $day_stamp);
        if ($dayNumber === 'w') {
            if ($currentDayNumber == 0) {
                $shiftToDay = 1;
            } else if ($currentDayNumber == 6) {
                $shiftToDay = 2;
            } else {
                $shiftToDay = 0;
            }
        } else {
            if ($currentDayNumber < $dayNumber) {
                $shiftToDay = $dayNumber - $currentDayNumber;
            } else if ($currentDayNumber == $dayNumber) {
                if ($noJumpIfSameDay == 0) {
                    $shiftToDay = 7;
                } else {
                    $shiftToDay = 0;
                }
            } else if ($currentDayNumber > $dayNumber) {
                $shiftToDay = 7 - $currentDayNumber + $dayNumber;
            } else {
                $shiftToDay = 0;
            }
        }

        $day_after = strtotime('+' . $shiftToDay . ' day', $day_stamp);
        return $day_after;
    }

    protected function getDayBefore($date, $dayNumber = 1, $shTimes = 0)
    {
        $day_stamp = strtotime($date);
        $currentDayNumber = (int) date('w', $day_stamp);

        if ($dayNumber === 'w') {
            if ($currentDayNumber == 0) {
                $shiftToDay = 2;
            } else if ($currentDayNumber == 6) {
                $shiftToDay = 1;
            } else {
                $shiftToDay = 0;
            }
        } else {
            if ($currentDayNumber > $dayNumber) {
                $shiftToDay = $currentDayNumber - $dayNumber;
            } else if ($currentDayNumber == $dayNumber) {
                $shiftToDay = 7;
            } else if ($currentDayNumber < $dayNumber) {
                $shiftToDay = $currentDayNumber + 7 - $dayNumber;
            }
        }
        //additional shift of weeks
        $shiftToDay = $shiftToDay + $shTimes * 7;

        $day_after = strtotime('-' . $shiftToDay . ' day', $day_stamp);
        return $day_after;
    }

    protected function getDayNearest($date, $dayNumber = 1)
    {
        $day_stamp = strtotime($date);
        $dayBefore = $this->getDayBefore($date, $dayNumber);
        $dayAfter = $this->getDayAfter($date, $dayNumber);
        if (2 * $day_stamp > $dayBefore + $dayAfter) {
            return $dayAfter;
        } else if (2 * $day_stamp < $dayBefore + $dayAfter) {
            return $dayBefore;
        } else if (2 * $day_stamp == $dayBefore + $dayAfter) {
            return $day_stamp;
        }
    }

    protected function getKey($key, $d_stamp)
    {
        $d_Y = date('Y', $d_stamp); //YEAR, OC

        if ($key == '25/12+0' || $key == '25/12+6') {
            if (date('m', $d_stamp) == '01') //if december
                $d_Y--;
        }
        if ($key == '06/01-6' || $key == '06/01-0') {
            if (date('m', $d_stamp) == '12') //if december
                $d_Y++;
        }
        if (preg_match("/(\d\d\/\d\d)(.)?(\!?\w)?#?(\d)?/u", $key, $out)) {
            $shDateO = str_replace("/", "-", $out['1']) . "-" . $d_Y; //date, OC, with slashes
            $sh_sign = $out['2'] ?? null; //operation sign
            $shDayn = $out['3'] ?? null; //day number,0 - sunday
            $shTimes = $out['4'] ?? null;
            $shDateStamp = strtotime('+13 days', strtotime($shDateO)); //timestamp NC
            $shDate = date('d-m-Y', $shDateStamp); //NC date
            $res_key = null;
            switch ($sh_sign) {
                case '+':
                    $res_key = date('d/m', strtotime('-13 days', $this->getDayAfter($shDate, $shDayn, $shTimes))); //OC, key
                    break;
                case '-':
                    $res_key = date('d/m', strtotime('-13 days', $this->getDayBefore($shDate, $shDayn, $shTimes))); //OC, key
                    break;
                case '~':
                    $res_key = date('d/m', strtotime('-13 days', $this->getDayNearest($shDate, $shDayn))); //OC, key
                    break;
                case '=':
                    $dayOfWeekNumber = date('w', $shDateStamp);
                    // Negation
                    if (isset($shDayn[0]) && $shDayn[0] === '!') {
                        if ($shDayn[1] === 'w' ?
                            // Not work day
                            ($dayOfWeekNumber === '6' || $dayOfWeekNumber === '0') :
                            // Not day number
                            $dayOfWeekNumber !== $shDayn[1]
                        ) {
                            $res_key = date('d/m', strtotime($shDateO)); //OC, key
                        }
                    } else {
                        if ($shDayn === 'w' ?
                            // Work day
                            ($dayOfWeekNumber !== '6' && $dayOfWeekNumber !== '0') :
                            // Day number
                            $dayOfWeekNumber === $shDayn
                        ) {
                            $res_key = date('d/m', strtotime($shDateO)); //OC, key
                        }
                    }
                    break;
                case '':
                    $res_key = date('d/m', strtotime($shDateO)); //OC, key
                    break;
            }
            return $res_key;
        }
    }

    protected function getNeperehod($dateStamp)
    {
        $reading_array = [];
        $d_stamp = strtotime('-13days', $dateStamp); //date stamp, OC
        if (!$this->neperehod) {
            return [];
        }
        foreach ($this->neperehod as $key => $value) {
            $res_key = $this->getKey($key, $d_stamp);
            if ($res_key && $res_key == date('d/m', $d_stamp)) {
                foreach ($value as $v) {
                    $reading_array[] = $v;
                }
            }
        }
        return $reading_array;
    }

    protected function getBReadings($dateStamp)
    {
        $key = date('j/n/Y', $dateStamp);
        return $this->bReadings[$key] ?? [];
    }

    protected function processSaints($saints, $dateStamp)
    {
        $saints = str_replace("#SR", "", $saints);
        $saints = str_replace("#NSR", "", $saints);
        $saints = preg_replace('/(?:\r\n|\r|\n)/', '<br>', $saints);
        $saints = preg_replace('/#TP(.)/', '<img src="/assets/icons/$1.svg"/>', $saints);
        $saints = str_replace('o.svg"', 'o.svg" class="invert" alt="Без знака"', $saints);
        $saints = str_replace('0.svg"', '0.svg" class="invert" alt="Без знака"', $saints);
        $saints = str_replace('1.svg"', '1.svg" class="invert" alt="Cовершается служба, не отмеченная в Типиконе никаким знаком"', $saints);
        $saints = str_replace('2.svg"', '2.svg" class="invert" alt="Совершается служба на шесть"', $saints);
        $saints = str_replace('3.svg"', '3.svg" alt="Совершается служба со славословием"', $saints);
        $saints = str_replace('4.svg"', '4.svg" alt="Совершается служба с полиелеем"', $saints);
        $saints = str_replace('5.svg"', '5.svg" alt="Совершается всенощное бдение"', $saints);
        $saints = str_replace('6.svg"', '6.svg" alt="Совершается служба великому празднику"', $saints);

        $saints = preg_replace_callback('#href="https://www.holytrinityorthodox.com/ru/calendar/los/(.*?).htm"#i', function ($matches) use ($dateStamp) {
            $key = $matches[1];
            $key = str_replace("/", "-", $key);
            $key = strtolower($key);
            return 'href="/#/date/' . date('Y-m-d', $dateStamp) . '/saint/' . $key . '" data-saint="' . $key . '"';
        }, $saints);
        return $saints;
    }
    protected function processPerehods($week, $dayOfWeekNumber, $gospelShift, $weekOld, $dateStampO, $year, $easterStamp)
    {
        $dayweek = $week . ';' . $dayOfWeekNumber; //concat key
        //OVERLAY GOSPEL SHIFT
        $dayweek_gospelshift = ($week + $gospelShift) . ';' . $dayOfWeekNumber; //concat key
        if (!isset($this->perehod[$dayweek])) {
            return [];
        }
        $perehods = $this->perehod[$dayweek];
        $ap = explode(';', $perehods[0]['readings']['Литургия']);
        $ap = explode(';', $perehods[0]['readings']['Литургия']);
        $manyReads = $ap[2] ?? null;
        $ap = $ap[0];
        $gs = explode(';', $this->perehod[$dayweek_gospelshift][0]['readings']['Литургия']);
        $gs = $gs[1] ?? null;
        if (($ap || $gs) && !$manyReads) {
            $perehods[0]['readings']['Литургия'] = $ap . ';' . $gs;
        }

        return $perehods;
    }

    protected function processWeekTitle($week_title, $week,  $weekOld)
    {
        if ($this->dayOfWeekNumber == 0) {
            $sedmned = "Неделя";
            $weekOld--;
        } else {
            $sedmned = "Седмица";
        }
        if (!$week_title) {
            if ($weekOld == 1) {
                $week_title = "Светлая седмица";
            } else if ($weekOld < 8) {
                if ($this->dayOfWeekNumber == 0) {
                    $weekOld++;
                }
                $week_title = "$sedmned $weekOld-я по Пасхе";
            } else if ($week > 43) {
                $week_title = "$sedmned " . ($week - 43) . "-я Великого поста";
            } else if ($weekOld < 46) {
                $week_title = "$sedmned " . ($weekOld - 7) . "-я по Пятидесятнице";
            }
        }
        return $week_title;
    }
    protected function processReadings($dayDataEntries)
    {
        $weekend = false;
        if ($this->dayOfWeekNumber == 0 || $this->dayOfWeekNumber == 6) {
            $weekend = true;
        }
        foreach ($dayDataEntries as $dayDataEntry) {
            if (!$dayDataEntry['reading_title']) {
                $dayDataEntry['reading_title'] = 'Рядовое';
            }
            $reading_title = $dayDataEntry['reading_title'];
            //if($dayDataEntry['prazdnikTitle'])
            //	$this->prazdnikTitle .= $dayDataEntry['prazdnikTitle'].'<br/>';
            if (isset($dayDataEntry['readings'])) {
                foreach ($dayDataEntry['readings'] as $serviceKey => $readings) {
                    //if(!$nr[$serviceKey][$reading_title])
                    if ($readings) {
                        if (!isset($nr[$serviceKey][$reading_title])) {
                            $nr[$serviceKey][$reading_title] = [];
                        }
                        $nr[$serviceKey][$reading_title][] = $readings;
                    }
                }
            }

            //order services
            $nr_or['Утреня'] = $nr['Утреня'] ?? null;
            $nr_or['1-й час'] = $nr['1-й час'] ?? null;
            $nr_or['3-й час'] = $nr['3-й час'] ?? null;
            $nr_or['6-й час'] = $nr['6-й час'] ?? null;
            $nr_or['9-й час'] = $nr['9-й час'] ?? null;
            $nr_or['Литургия'] = $nr['Литургия'] ?? null;
            $nr_or['Вечерня'] = $nr['Вечерня'] ?? null;
            $nr_or['На освящении воды'] = $nr['На освящении воды'] ?? null;
        }
        // if ((count($nr_or['Утреня'] ?? []) > 1) && $nr_or['Утреня']['Воскресное евангелие']) {
        //unset sunday saint's matins?
        // }
        $resultArray = [];
        foreach ($nr_or as $serviceKey => $nr2) {
            if ($this->noLiturgy && $serviceKey == 'Литургия') {
                continue;
            }
            if ($nr2) {
                foreach ($nr2 as $rtitle => $_readings) {
                    foreach ($_readings as $readings) {
                        $readingFound = false;
                        $readings = str_replace('–', '-', $readings);
                        if ($rtitle === 'Рядовое' && $this->skipRjadovoe && $serviceKey === 'Литургия') {
                            continue;
                        }
                        $fragments = [];
                        if (strpos($readings, ' ') === false) { // this is zachalo arrays: 25;Jh25
                            foreach (explode(';', $readings) as $reading) {
                                $reading_ex = explode('/', $reading);
                                if ($weekend && isset($reading_ex[1])) {
                                    $reading = $reading_ex[1];
                                } else {
                                    $reading = $reading_ex[0];
                                }
                                if (isset($this->zachala[$reading])) {
                                    $readingFound = true;
                                    $fragments[] = trim($this->zachala[$reading]);
                                }
                            }
                        } else { //this is verse: Мих. IV, 2-3; 5; VI, 2-5; 8; V, 4
                            $fragments[] = trim($readings);
                            $readingFound = true;
                        }
                        // If reading wasn't found just output it as is
                        if (!$readingFound) {
                            $fragments[] = trim($readings);
                        }
                        if (!isset($resultArray[$serviceKey])) {
                            $resultArray[$serviceKey] = [];
                        }
                        $resultArray[$serviceKey][$rtitle] = array_merge($resultArray[$serviceKey][$rtitle] ?? [], $fragments);
                    }
                }
            }
        }

        return $resultArray;
    }

    protected function check_skipRjadovoe($saints)
    {
        //skip rjadovoje reading if not sunday and Great prazdnik
        $skipRjadovoe = false;
        if ((strpos($saints, "#TP6") || strpos($saints, "#TP5")) && ($this->dayOfWeekNumber != 0))
            $skipRjadovoe = true;
        //skip rjadovoe
        if (strpos($saints, "#SR") !== false)
            $skipRjadovoe = true;
        if (strpos($saints, "#NSR") !== false)
            $skipRjadovoe = false;
        return $skipRjadovoe;
    }

    protected function getDayData($perehod, $lang)
    {
        $airtableData = $perehod ? getPerehod() : getNeperehod();
        foreach ($airtableData as $line) {
            if (!isset($line['Дата'])) {
                continue;
            }
            $langMap = [
                'csj' => 'Цся',
                'ru' => 'Рус',
            ];
            if (!isset($line['Язык']) || $line['Язык'] !== $langMap[$lang]) {
                continue;
            }
            $data = [];
            $data['week_title'] = $line['Неделя'] ?? '';
            $data['saints'] = $line['Святые'] ?? '';
            $data['reading_title'] = $line['Заглавие чтения'] ?? '';
            $data['readings']['Утреня'] = $line['Утреня'] ?? '';
            $data['readings']['Литургия'] = $line['Литургия'] ?? '';
            $data['readings']['Вечерня'] = $line['Вечерня'] ?? '';
            $data['readings']['1-й час'] = $line['1-й час'] ?? '';
            $data['readings']['3-й час'] = $line['3-й час'] ?? '';
            $data['readings']['6-й час'] = $line['6-й час'] ?? '';
            $data['readings']['9-й час'] = $line['9-й час'] ?? '';
            $data['readings']['На освящении воды'] = $line['На освящении воды'] ?? '';
            $groups = [
                'liturgy' => ['Прокимен', 'Аллилуарий', 'Причастен', 'Входной стих', 'Вместо Трисвятого', 'Задостойник', 'Отпуст'],
                'shared' => ['Отпуст Синаксарный', 'Тропари', 'Кондаки', 'Величания', 'Величания-службы', 'Эксапостиларии', 'Богородичен', 'Тропари-службы', 'Кондаки-службы', 'Богородичен-службы'],
                'vespers' => ['Cтихиры на Господи взываю', 'Cтихиры на стихах', 'Прокимен вечерни', 'Прокимен триоди 1', 'Прокимен триоди 2'],
                'matins' => ['Cтихиры на хвалите', 'Стихиры после 50 псалма', 'Прокимен утрени', 'Степенны']
            ];
            foreach ($groups as $groupName => $group) {
                foreach ($group as $partKey) {
                    $fieldValue = $line[$partKey] ?? '';
                    if ($fieldValue) {
                        $data['parts'][$groupName][$partKey] = $fieldValue;
                    }
                }
            }
            foreach (explode(',', $line['Дата']) as $weekday) {
                if ($perehod) {
                    $this->perehod[$weekday][] = $data;
                } else {
                    $this->neperehod[$weekday][] = $data;
                }
            }
        }
    }

    protected function init($date, $lang, $weekToEaster)
    {
        if (!file_exists('Data')) {
            mkdir('Data', 0777, true);
        }
        $googleUrl = 'https://docs.google.com/spreadsheet/pub?hl=en&hl=en&key=0AnIrRiVoiOUSdENKckd0Vm1RbVhUMGVOQWNIZUNBUmc&single=true&output=csv&gid=';

        $this->getDayData(true, $lang);
        $this->getDayData(false, $lang);

        $filename = 'Data/cache_zachala_apostol.csv';
        $gid = 3;
        if ($this->isDebug) {
            unlink($filename);
        }
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $key = $line[0];
            $reading = $line[1];
            $this->zachala[$key] = $reading;
        }
        fclose($file);

        $filename = 'Data/cache_zachala_gospel.csv';
        $gid = 18;
        if ($this->isDebug) {
            unlink($filename);
        }
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $key = $line[0];
            $reading = $line[1];
            $this->zachala[$key] = $reading;
        }
        fclose($file);

        $filename = 'Data/cache_bReadings.csv';
        $gid = 19;
        if ($this->isDebug) {
            unlink($filename);
        }
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $date = $line[0];
            if ($line[1]) {
                $this->bReadings[$date][$weekToEaster > -7 && !($this->dayOfWeekNumber === 6 || $this->dayOfWeekNumber === 0) ? 'На 6-м часе' : 'Утром']['unnamed'][] = $line[1];
            }
            if ($line[2]) {
                $this->bReadings[$date]['Вечером']['unnamed'][] = $line[2];
            }
        }
        fclose($file);

        $file = fopen('Data/static_sunday_matins_gospels.csv', 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $this->sundayMatinsGospels[$line[0]] = $line[1];
        }
        fclose($file);

        $filename = 'Data/cache_saints.csv';
        $gid = 5;
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $this->saints[$line[0]] = $line[1];
        }
        fclose($file);
    }

    // Flatten $dayDataEntries
    protected function reduceDayData($dayDataEntries)
    {
        $result = smartMerge($dayDataEntries, ['saints'], ['week_title']);

        $partsEntries = array_map(function ($d) {
            return $d['parts'] ?? [];
        }, $dayDataEntries);
        $parts = [];

        foreach ($partsEntries as $groups) {
            foreach ($groups as $groupName => $group) {
                foreach ($group as $partName => $part) {
                    if (endsWith($partName, '-службы')) {
                        continue;
                    }
                    if (!isset($parts[$groupName][$partName])) {
                        $parts[$groupName][$partName] = [];
                    }
                    if ($part) {
                        if (is_string($part)) {
                            $part = styleHtml($this->parsedown->text($part));
                        }
                        $services = null;
                        if (isset($group[$partName . '-службы'])) {
                            $services = $group[$partName . '-службы'];
                        }
                        $parts[$groupName][$partName][] = ["value" => $part, "services" => $services];
                    }
                }
            }
        }
        $result['parts'] = $parts;
        return $result;
    }
    /**
     * @param string $date
     * @return string
     */
    public function parts($date = null, $lang = 'ru')
    {
        return $this->run($date, $lang, true);
    }
    /**
     * @param string $date
     * @return string
     */
    public function run($date = null, $lang = 'ru', $isParts = false)
    {
        $debug = '';
        if (!$date)
            $date = date('Ymd');

        $date = date('Ymd', strtotime("-13 days", strtotime($date)));
        $dateStampO = strtotime($date);
        $dateStamp = strtotime("+13 days", $dateStampO);
        $this->dayOfWeekNumber = date('N', $dateStamp) % 7;

        $year = date("Y", $dateStamp);
        $easterStamp = easter($year);

        if ($easterStamp > $dateStamp) {
            $year = $year - 1;
            $easterStamp = easter($year);
        }
        $nextEasterStamp = easter($year + 1);
        $debug .= "год" . $year;

        $week = datediff('ww', $easterStamp, $dateStamp, true) + 1;
        $weekToEaster = datediff('ww', $nextEasterStamp, $dateStamp, true) + 1;

        $this->init($date, $lang, $weekToEaster);

        $fast = null;
        if (strtotime('15-11-' . $year) <= $dateStampO && $dateStampO <= strtotime('24-12-' . ($year))) {
            $fast = "Рождественский (Филиппов) пост";
        }
        if (strtotime('01-08-' . $year) <= $dateStampO && $dateStampO <= strtotime('14-08-' . ($year))) {
            $fast = "Успенский пост";
        }
        if ((11 <= $week) && $dateStampO <= strtotime('29-06-' . ($year))) {
            $fast = "Петров пост";
        }


        $sunday_after_krest = $this->getDayAfter('27-09-' . $year, 0);
        $mondayAfterSundayAfterKrest = strtotime("+1 day", $sunday_after_krest);
        $week_after_krest = datediff('ww', $easterStamp, $mondayAfterSundayAfterKrest, true) + 1;
        $krestDiff = 25 - $week_after_krest;
        $monday18thWeek = strtotime("+24 weeks 1 day", $easterStamp);

        if ($dateStamp >= $mondayAfterSundayAfterKrest) { //in any case, start to read Luke only on Monday after Sunday after Krestovozdvijenije
            $gospelShift = $krestDiff;
        } else if ($dateStamp >= $monday18thWeek) {
            $gospelShift = -6 + $krestDiff; //shift back to Mt and skip Mk weeks
        } else {
            $gospelShift = 0;
        }

        $prosv = strtotime('19-01-' . ($year + 1));
        $mondayAfterProsv = $this->getDayAfter('19-01-' . ($year + 1), 1, 0, 1); //NB: Weird stuff, but if Prosv is on Monday, the reset should occur the same day
        $weekAfterProsv = $this->getDayAfter('19-01-' . ($year + 1), 0);

        $weekOld = $week; //save old week number, neccessary for matins order
        if ($week > 40 || $dateStamp >= $mondayAfterProsv) {
            if (
                ($weekToEaster == -12 && $this->dayOfWeekNumber != 0)
                ||
                ($weekToEaster == -11 && $this->dayOfWeekNumber == 0)
            ) {
                $debug .= "weekToEaster-14!";
                $weekToEaster = $weekToEaster - 14;
            } else if ($weekToEaster <= -12) {
                $debug .= "weekToEaster+0!";
                $weekToEaster = $weekToEaster;
            }
            $debug .= "week season!";
            $week = 50 + $weekToEaster;
        }
        if ($dateStamp >= $mondayAfterProsv) {
            $debug .= "gospel shift reset!";
            $gospelShift = 0;
        }
        $debug .= "Неделя" . $week;
        $debug .= "Неделя_old" . $weekOld;
        $debug .= "Понедельник по крестовоздвижению: " . date("Ymd", $mondayAfterSundayAfterKrest) . "| 17-я неделя " . date('Ymd', $monday18thWeek) . "<br/>";
        $debug .= "Неделя по просвящении " . date('Ymd', $weekAfterProsv);
        $debug .= "<br/>Сдвиг: крест" . $krestDiff . "|еванг" . $gospelShift;
        $debug .= "<br/>Неделя по пасхе: " . $week;
        $debug .= "<br/>Недель до следующей пасхи: " . $weekToEaster;


        //matins sunday
        $matinsZachalo = null;
        $matins_key = null;
        if ($this->dayOfWeekNumber == 0) {
            if ($weekOld >= 9) {
                $matins_key = ($weekOld - 9) % 11 + 1;
            } else if (1 < $weekOld) {
                $matins_pre50 = explode(",", "1,3,4,7,8,10,9");
                $matins_key = $matins_pre50[$weekOld - 2];
            }
            $matinsZachalo = $matins_key ? $this->sundayMatinsGospels[$matins_key] : null;
        }

        //glass
        $glas = (($weekOld - 1) % 8);
        $glas = $glas ? $glas : 8;
        if (($weekOld == 1) || ($weekOld == 8) || ($week == 50)) {
            $glas = null;
        }

        $perehods = $this->processPerehods($week, $this->dayOfWeekNumber, $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);


        if ($week + $gospelShift == 36 && $this->dayOfWeekNumber == 0) {
            $debug .= "gospel shifted from praotez";
            $praotecStamp = strtotime(str_replace('/', '-', $this->getKey('25/12-0#1', $dateStampO) . "/" . $year));

            $ap = explode(";", $perehods[0]['readings']['Литургия']);
            $manyReads = isset($ap[2]);
            $ap = $ap[0];
            $processedPerehodForPraotez = $this->processPerehods(datediff('ww', $easterStamp, $praotecStamp, true) + 3, "0", $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $gs = explode(";", $processedPerehodForPraotez[0]['readings']['Литургия']);
            $gs = $gs[1];
            if (($ap || $gs) && !$manyReads) {
                $perehods[0]['readings']['Литургия'] = $ap . ";" . $gs;
            }
        }
        if ($week == 37 && $this->dayOfWeekNumber == 0) {
            $debug .= "apostol shifted from praotez";
            $praotecStamp = strtotime(str_replace('/', '-', $this->getKey('25/12-0#1', $dateStampO) . "/" . $year));
            $processedPerehodForPraotez = $this->processPerehods(datediff('ww', $easterStamp, $praotecStamp, true) + 3, "0", $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $ap = explode(";", $processedPerehodForPraotez[0]['readings']['Литургия']);
            $ap = $ap[0];
            $gs = explode(";", $perehods[0]['readings']['Литургия']);
            $manyReads = isset($gs[2]);
            $gs = $gs[1];
            if (($ap || $gs) && !$manyReads)
                $perehods[0]['readings']['Литургия'] = $ap . ";" . $gs;
        }

        // Merge perehod and neperehod data entries for given day
        $neperehodArray = $this->getNeperehod($dateStamp);
        if (!$perehods) {
            $dayDataEntries = $neperehodArray;
        } else if (!$neperehodArray) {
            $dayDataEntries = $perehods;
        } else {
            $dayDataEntries = array_merge($perehods, $neperehodArray);
        }
        $dayData = $this->reduceDayData($dayDataEntries);

        $saintsThisDay = trim($this->saints[date('d/m', $dateStampO)]);

        if ($dayData['saints'] && $saintsThisDay) {
            $dayData['saints'] .= "<br/>";
        }
        $dayData['saints'] .= $saintsThisDay;


        $this->skipRjadovoe = $this->check_skipRjadovoe($dayData['saints']);

        if (!$this->skipRjadovoe) {
            //OVERLAY SUNDAY MATINS
            $mat['readings']['Утреня'] = $matinsZachalo;
            $mat['reading_title'] = 'Воскресное евангелие';
            array_unshift($dayDataEntries, $mat);
        }

        //PERENOS CHTENIJ
        if (($this->dayOfWeekNumber != 0) && (!$this->skipRjadovoe)) { //not on Sunday, check for move forward
            $next_dateStampO = strtotime("+1 days", $dateStampO);
            $next_dateStamp = strtotime("+1 days", $dateStamp);

            //combine saints
            $t = $this->getNeperehod($next_dateStamp);
            $next_saints = $t['0']['saints'] ?? '';
            $r = $this->processPerehods($week, $this->normalizeDayOfWeek($this->dayOfWeekNumber + 1), $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $next_saints .= $r['0']['saints'] ?? '';
            $next_saints .= $this->saints[date('d/m', $next_dateStampO)];

            if ($this->check_skipRjadovoe($next_saints)) {
                $debug .= "<br>something holy is around";

                $r['0']['reading_title'] = 'За ' . $this->dayOfWeekNames[$this->normalizeDayOfWeek($this->dayOfWeekNumber + 1)];
                $dayDataEntries = array_merge($dayDataEntries, $r);
            }
        }
        //refactor at will
        //this must match http://c.psmb.ru/pravoslavnyi-kalendar/date/20130803?debug=1, also look at the previous days
        if (($this->dayOfWeekNumber != 0) && (!$this->skipRjadovoe)) { //not on Sunday, check for move forward
            $next_dateStampO = strtotime("-1 days", $dateStampO);
            $next_dateStamp = strtotime("-1 days", $dateStamp);

            //combine saints
            $t = $this->getNeperehod($next_dateStamp);
            $next_saints = $t['0']['saints'] ?? '';
            $r = $this->processPerehods($week, $this->normalizeDayOfWeek($this->dayOfWeekNumber - 1), $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $next_saints .= $r['0']['saints'] ?? '';
            $next_saints .= $this->saints[date('d/m', $next_dateStampO)];

            if ($first_check = $this->check_skipRjadovoe($next_saints)) {
                $next_dateStampO = strtotime("-2 days", $dateStampO);
                $next_dateStamp = strtotime("-2 days", $dateStamp);

                //combine saints
                $t = $this->getNeperehod($next_dateStamp);
                $next_saints = $t['0']['saints'] ?? '';
                $r2 = $this->processPerehods($week, $this->normalizeDayOfWeek($this->dayOfWeekNumber - 2), $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
                $next_saints .= $r2['0']['saints'] ?? '';
                $next_saints .= $this->saints[date('d/m', $next_dateStampO)];
                if ($this->check_skipRjadovoe($next_saints) || ($this->dayOfWeekNumber == 2)) {
                    $debug .= "<br>something very holy is around!";

                    $r['0']['reading_title'] = 'За ' . $this->dayOfWeekNames[$this->normalizeDayOfWeek($this->dayOfWeekNumber - 1)];
                    $dayDataEntries = array_merge($r, $dayDataEntries);
                }
            }
        }

        //skip rjad on sochelnik HACK HACK HACK
        if ($this->dayOfWeekNumber == 5 && ($date == $year . '1222')) {
            $this->noLiturgy = true;
        }
        $debug .= '<br/>пропуск рядового чтения:' . $this->skipRjadovoe;

        if ($isParts) {
            return $dayData['parts'];
        }

        $readings = $this->processReadings($dayDataEntries);

        $staticData = $this->getStaticData($dateStamp);
        if ($staticData) {
            $staticReadings = [];
            foreach ($staticData['readings'] as $serviceType => $readingGroup) {
                $serviceType = $serviceType == 'Утр' ? 'Утреня' : $serviceType;
                $serviceType = $serviceType == 'Лит' ? 'Литургия' : $serviceType;
                foreach ($readingGroup as $readingType => $reading) {
                    $readingType = $readingType == '' ? 'Рядовое' : $readingType;
                    $readingType = str_replace('и за ', 'За ', $readingType);
                    $reading = preg_replace('/(.*),$/i', '$1.', $reading);

                    $staticReadings[$serviceType][$readingType] = array_merge($staticReadings[$serviceType][$readingType] ?? [], $reading);
                }
            }
            if (isset($staticReadings["Утреня"])) {
                $readings['Утреня'] = $staticReadings["Утреня"];
            }
            if (isset($staticReadings["Литургия"])) {
                $readings['Литургия'] = $staticReadings["Литургия"];
            }

            // Restore sorting order
            if (isset($readings['Утреня'])) {
                $readings_or['Утреня'] = $readings['Утреня'];
            }
            if (isset($readings['1-й час'])) {
                $readings_or['1-й час'] = $readings['1-й час'];
            }
            if (isset($readings['3-й час'])) {
                $readings_or['3-й час'] = $readings['3-й час'];
            }
            if (isset($readings['6-й час'])) {
                $readings_or['6-й час'] = $readings['6-й час'];
            }
            if (isset($readings['9-й час'])) {
                $readings_or['9-й час'] = $readings['9-й час'];
            }
            if (isset($readings['Литургия'])) {
                $readings_or['Литургия'] = $readings['Литургия'];
            }
            if (isset($readings['Литургия'])) {
                $readings_or['Литургия'] = $readings['Литургия'];
            }
            if (isset($readings['Вечерня'])) {
                $readings_or['Вечерня'] = $readings['Вечерня'];
            }
            if (isset($readings['На освящении воды'])) {
                $readings_or['На освящении воды'] = $readings['На освящении воды'];
            }
            $readings = $readings_or;

            // $dynamicData['comment'] = preg_replace('/<a\s+href="([^"]+)"\s*>/', '<a class="reading" href="http://bible.psmb.ru/bible/book/$1/">', $staticData['comment'] ?? '');
        }

        $jsonArray = [
            "title" => $this->processWeekTitle($dayData['week_title'], $week, $weekOld) ?? null,
            "glas" => $glas ?? null,
            "lent" => $fast ?? null,
            "matinsGospelKey" => $matins_key ?? null,
            "readings" => $readings ?? null,
            'bReadings' => $this->getBReadings($dateStamp),
            "saints" => $this->processSaints($dayData['saints'], $dateStamp) ?? null,
        ];

        return $jsonArray;
    }
}
