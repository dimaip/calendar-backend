<?php
require_once('init.php');
require('functions.php');
require('bible.php');

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
        $currentDayNumber = date('N', $day_stamp);
        if ($currentDayNumber == 7)
            $currentDayNumber = 0;
        if ($currentDayNumber < $dayNumber) {
            $shiftToDay = $dayNumber - $currentDayNumber;
        } else if ($currentDayNumber == $dayNumber) {
            if ($noJumpIfSameDay == 0) {
                $shiftToDay = 7;
            }
        } else if ($currentDayNumber > $dayNumber) {
            $shiftToDay = 7 - $currentDayNumber + $dayNumber;
        }
        $day_after = strtotime('+' . $shiftToDay . ' day', $day_stamp);
        return $day_after;
    }

    protected function getDayBefore($date, $dayNumber = 1, $shTimes = 0)
    {
        $day_stamp = strtotime($date);
        $currentDayNumber = (int) date('N', $day_stamp);
        if ($currentDayNumber == 7) {
            $currentDayNumber = 0;
        }

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
        if (preg_match("/(\d\d\/\d\d)(.)?(\w)?#?(\d)?/u", $key, $out)) {
            $shDateO = str_replace("/", "-", $out['1']) . "-" . $d_Y; //date, OC, with slashes
            $sh_sign = $out['2'] ?? null; //operation sign
            $shDayn = $out['3'] ?? null; //day number,0 - sunday
            $shTimes = $out['4'] ?? null;
            $shDateStamp = strtotime('+13 days', strtotime($shDateO)); //timestamp NC
            $shDate = date('d-m-Y', $shDateStamp); //NC date
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

        foreach ($this->neperehod as $key => $value) {
            $res_key = $this->getKey($key, $d_stamp);
            if ($res_key == date('d/m', $d_stamp)) {
                foreach ($value as $v) {
                    $reading_array[] = $v;
                }
            }
        }
        return $reading_array;
    }

    protected function getBReadings($dateStamp)
    {
        foreach ($this->bReadings as $key => $value) {
            if ($key === date('j/n/Y', $dateStamp)) {
                foreach ($value as $v) {
                    return $v;
                }
            }
        }
        return [];
    }

    protected function process_perehods($week, $dayOfWeekNumber, $gospelShift, $weekOld, $dateStampO, $year, $easterStamp)
    {
        $dayweek = $week . ';' . $dayOfWeekNumber; //concat key
        //OVERLAY GOSPEL SHIFT
        $dayweek_gospelshift = ($week + $gospelShift) . ';' . $dayOfWeekNumber; //concat key
        $perehods = $this->perehod[$dayweek];
        $ap = explode(';', $perehods[0]['reading']['Литургия']);
        $ap = explode(';', $perehods[0]['reading']['Литургия']);
        $manyReads = $ap[2] ?? null;
        $ap = $ap[0];
        $gs = explode(';', $this->perehod[$dayweek_gospelshift][0]['reading']['Литургия']);
        $gs = $gs[1] ?? null;
        if (($ap || $gs) && !$manyReads) {
            $perehods[0]['reading']['Литургия'] = $ap . ';' . $gs;
        }

        return $perehods;
    }

    protected function formatReading($arr, $weekend)
    {
        foreach ($arr as $array) {
            if (!$array['reading_title'])
                $array['reading_title'] = 'Рядовое';
            $reading_title = $array['reading_title'];
            //if($array['prazdnikTitle'])
            //	$this->prazdnikTitle .= $array['prazdnikTitle'].'<br/>';
            foreach ($array['reading'] as $serviceKey => $readings) {
                //if(!$nr[$serviceKey][$reading_title])
                if ($readings) {
                    if (!isset($nr[$serviceKey][$reading_title])) {
                        $nr[$serviceKey][$reading_title] = [];
                    }
                    $nr[$serviceKey][$reading_title][] = $readings;
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
            $fl = false;
            if ($this->noLiturgy && $serviceKey == 'Литургия')
                continue;
            if ($nr2) {
                foreach ($nr2 as $rtitle => $_readings) {
                    foreach ($_readings as $readings) {
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
                                    $fl = true;
                                    $fragments[] = trim($this->zachala[$reading]);
                                }
                            }
                        } else { //this is verse: Мих. IV, 2-3; 5; VI, 2-5; 8; V, 4
                            $fragments[] = $readings;
                        }
                        if (!$fl) {
                            $fragments[] = trim($readings);
                        }
                        if (!isset($resultArray[$serviceKey]))
                            $resultArray[$serviceKey] = [];
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

    protected function init($date)
    {
        if (!file_exists('Data')) {
            mkdir('Data', 0777, true);
        }
        $googleUrl = 'https://docs.google.com/spreadsheet/pub?hl=en&hl=en&key=0AnIrRiVoiOUSdENKckd0Vm1RbVhUMGVOQWNIZUNBUmc&single=true&output=csv&gid=';

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

        $filename = 'Data/cache_perehod.csv';
        $gid = 4;
        if ($this->isDebug) {
            unlink($filename);
        }
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $weekday = $line[0];
            unset($neperehodArray);
            $perehod_array['week_title'] = $line[1];
            $perehod_array['saints'] = $line[2];
            $perehod_array['reading_title'] = $line[3];
            $perehod_array['reading']['Утреня'] = $line[4];
            $perehod_array['reading']['Литургия'] = $line[5];
            $perehod_array['reading']['Вечерня'] = $line[6];
            $perehod_array['reading']['1-й час'] = $line[7];
            $perehod_array['reading']['3-й час'] = $line[8];
            $perehod_array['reading']['6-й час'] = $line[9];
            $perehod_array['reading']['9-й час'] = $line[10];
            $perehod_array['reading']['На освящении воды'] = $line[11];
            $perehod_array['prayers'] = $line[12];
            $perehod_array['prokimen'] = $line[13];
            $perehod_array['aliluja'] = $line[14];
            $perehod_array['prichasten'] = $line[15];
            $this->perehod[$weekday][] = $perehod_array;
        }
        fclose($file);

        $filename = 'Data/cache_neperehod.csv';
        $gid = 0;
        if ($this->isDebug) {
            unlink($filename);
        }
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $date = $line[0];
            $neperehodArray['week_title'] = $line[1];
            $neperehodArray['saints'] = $line[2];
            $neperehodArray['reading_title'] = $line[3];
            $neperehodArray['reading']['Утреня'] = $line[4];
            $neperehodArray['reading']['Литургия'] = $line[5];
            $neperehodArray['reading']['Вечерня'] = $line[6];
            $neperehodArray['reading']['1-й час'] = $line[7];
            $neperehodArray['reading']['3-й час'] = $line[8];
            $neperehodArray['reading']['6-й час'] = $line[9];
            $neperehodArray['reading']['9-й час'] = $line[10];
            $neperehodArray['reading']['На освящении воды'] = $line[11];
            $neperehodArray['prayers'] = $line[12];
            $neperehodArray['prokimen'] = $line[13];
            $neperehodArray['aliluja'] = $line[14];
            $neperehodArray['prichasten'] = $line[15];
            $this->neperehod[$date][] = $neperehodArray;
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
            $bReadingsArray = [];
            if ($line[1]) {
                $bReadingsArray['Утром'] = ['unnamed' => [$line[1]]];
            }
            if ($line[2]) {
                $bReadingsArray['Вечером'] = ['unnamed' => [$line[2]]];
            }
            $this->bReadings[$date][] = $bReadingsArray;
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

    /**
     * @param string $date
     * @return string
     */
    public function run($date = null)
    {
        $debug = '';
        if (!$date)
            $date = date('Ymd');
        $this->init($date);

        $date_next = date("Ymd", strtotime("+1 days", strtotime($date)));
        $date_prev = date("Ymd", strtotime("-1 days", strtotime($date)));
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
        $weekToEaster = datediff('ww', $nextEasterStamp, $dateStamp, true) + 1;

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
        if (($weekOld == 1) || ($week == 50) || ($weekOld == 2))
            $glas = null;

        $perehods = $this->process_perehods($week, $this->dayOfWeekNumber, $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);


        if ($week + $gospelShift == 36 && $this->dayOfWeekNumber == 0) {
            $debug .= "gospel shifted from praotez";
            $praotecStamp = strtotime(str_replace('/', '-', $this->getKey('25/12-0#1', $dateStampO) . "/" . $year));

            $ap = explode(";", $perehods[0]['reading']['Литургия']);
            $manyReads = isset($ap[2]);
            $ap = $ap[0];
            $processedPerehodForPraotez = $this->process_perehods(datediff('ww', $easterStamp, $praotecStamp, true) + 3, "0", $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $gs = explode(";", $processedPerehodForPraotez[0]['reading']['Литургия']);
            $gs = $gs[1];
            if (($ap || $gs) && !$manyReads) {
                $perehods[0]['reading']['Литургия'] = $ap . ";" . $gs;
            }
        }
        if ($week == 37 && $this->dayOfWeekNumber == 0) {
            $debug .= "apostol shifted from praotez";
            $praotecStamp = strtotime(str_replace('/', '-', $this->getKey('25/12-0#1', $dateStampO) . "/" . $year));
            $processedPerehodForPraotez = $this->process_perehods(datediff('ww', $easterStamp, $praotecStamp, true) + 3, "0", $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $ap = explode(";", $processedPerehodForPraotez[0]['reading']['Литургия']);
            $ap = $ap[0];
            $gs = explode(";", $perehods[0]['reading']['Литургия']);
            $manyReads = isset($gs[2]);
            $gs = $gs[1];
            if (($ap || $gs) && !$manyReads)
                $perehods[0]['reading']['Литургия'] = $ap . ";" . $gs;
        }


        //OVERLAY SUNDAY MATINS
        $mat['reading']['Утреня'] = $matinsZachalo;
        $mat['reading_title'] = 'Воскресное евангелие';
        $perehods[] = $mat;

        $neperehodArray = $this->getNeperehod($dateStamp);
        if (!$perehods)
            $arrayz = $neperehodArray;
        else if (!$neperehodArray)
            $arrayz = $perehods;
        else
            $arrayz = array_merge($perehods, $neperehodArray);

        $saints = '';
        $prayers = '';
        $week_title = '';
        $prokimen = [];
        $aliluja = [];
        $prichasten = [];
        //Saints
        foreach ($arrayz as $ar) {
            if (isset($ar['saints'])) {
                if ($saints && $ar['saints']) {
                    $saints .= "<br/>";
                }
                $saints .= trim($ar['saints']);
            }
            if (isset($ar['prayers'])) {
                if ($prayers && $ar['prayers']) {
                    $prayers .= '<br/>';
                }
                $prayers .= trim($ar['prayers']);
            }
            if (isset($ar['week_title'])) {
                $week_title .= trim($ar['week_title']);
            }
            if (isset($ar['prokimen']) && $ar['prokimen']) {
                $prokimen[] = json_decode($ar['prokimen'], true);
            }
            if (isset($ar['aliluja']) && $ar['aliluja']) {
                $aliluja[] = json_decode($ar['aliluja'], true);
            }
            if (isset($ar['prichasten']) && $ar['prichasten']) {
                $prichasten[] = json_decode($ar['prichasten'], true);
            }
        }

        $prokimen = array_values(array_filter($prokimen));
        $aliluja = array_values(array_filter($aliluja));
        $prichasten = array_values(array_filter($prichasten));

        $saintsThisDay = trim($this->saints[date('d/m', $dateStampO)]);

        if ($saints && $saintsThisDay) {
            $saints .= "<br/>";
        }
        $saints .= $saintsThisDay;


        $this->skipRjadovoe = $this->check_skipRjadovoe($saints);

        //PERENOS CHTENIJ
        if (($this->dayOfWeekNumber != 0) && (!$this->skipRjadovoe)) { //not on Sunday, check for move forward
            $next_dateStampO = strtotime("+1 days", $dateStampO);
            $next_dateStamp = strtotime("+1 days", $dateStamp);

            //combine saints
            $t = $this->getNeperehod($next_dateStamp);
            $next_saints = $t['0']['saints'] ?? '';
            $r = $this->process_perehods($week, $this->normalizeDayOfWeek($this->dayOfWeekNumber + 1), $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $next_saints .= $r['0']['saints'] ?? '';
            $next_saints .= $this->saints[date('d/m', $next_dateStampO)];

            if ($this->check_skipRjadovoe($next_saints)) {
                $debug .= "<br>something holy is around";

                $r['0']['reading_title'] = 'За ' . $this->dayOfWeekNames[$this->normalizeDayOfWeek($this->dayOfWeekNumber + 1)];
                $arrayz = array_merge($arrayz, $r);
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
            $r = $this->process_perehods($week, $this->normalizeDayOfWeek($this->dayOfWeekNumber - 1), $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
            $next_saints .= $r['0']['saints'] ?? '';
            $next_saints .= $this->saints[date('d/m', $next_dateStampO)];

            if ($first_check = $this->check_skipRjadovoe($next_saints)) {
                $next_dateStampO = strtotime("-2 days", $dateStampO);
                $next_dateStamp = strtotime("-2 days", $dateStamp);

                //combine saints
                $t = $this->getNeperehod($next_dateStamp);
                $next_saints = $t['0']['saints'] ?? '';
                $r2 = $this->process_perehods($week, $this->normalizeDayOfWeek($this->dayOfWeekNumber - 2), $gospelShift, $weekOld, $dateStampO, $year, $easterStamp);
                $next_saints .= $r2['0']['saints'] ?? '';
                $next_saints .= $this->saints[date('d/m', $next_dateStampO)];
                if ($this->check_skipRjadovoe($next_saints) || ($this->dayOfWeekNumber == 2)) {
                    $debug .= "<br>something very holy is around!";

                    $r['0']['reading_title'] = 'За ' . $this->dayOfWeekNames[$this->normalizeDayOfWeek($this->dayOfWeekNumber - 1)];
                    $arrayz = array_merge($r, $arrayz);
                }
            }
        }


        if ($this->dayOfWeekNumber == 0 && $glas && $week != 8) {
            require('Data/static_sunday_troparion.php');
            if ($prayers && $sunday_troparion[$glas])
                $prayers .= "<br/>";
            $prayers .= $sunday_troparion[$glas];
        }


        //skip rjad on sochelnik HACK HACK HACK
        if ($this->dayOfWeekNumber == 5 && ($date == $year . '1222'))
            $this->noLiturgy = true;
        $debug .= '<br/>пропуск рядового чтения:' . $this->skipRjadovoe;

        $saints = str_replace("#SR", "", $saints);
        $saints = str_replace("#NSR", "", $saints);
        $saints = preg_replace('/(?:\r\n|\r|\n)/', '<br>', $saints);
        $saints = preg_replace('/#TP(.)/', '<img src="/assets/icons/$1.svg"/>', $saints);
        $saints = str_replace('1.gif"', '1.gif" title="Cовершается служба, не отмеченная в Типиконе никаким знаком"', $saints);
        $saints = str_replace('2.gif"', '2.gif" title="Совершается служба на шесть"', $saints);
        $saints = str_replace('3.gif"', '3.gif" title="Совершается служба со славословием"', $saints);
        $saints = str_replace('4.gif"', '4.gif" title="Совершается служба с полиелеем"', $saints);
        $saints = str_replace('5.gif"', '5.gif" title="Совершается всенощное бдение"', $saints);
        $saints = str_replace('6.gif"', '6.gif" title="Совершается служба великому празднику"', $saints);


        //format reading
        $weekend = false;
        if ($this->dayOfWeekNumber == 0 || $this->dayOfWeekNumber == 6) {
            $weekend = true;
        }

        $reading_str = $this->formatReading($arrayz, $weekend);

        //title
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

        $dayweek = ($week + $gospelShift) . ";" . $this->dayOfWeekNumber;

        $saints = preg_replace_callback('#href="https://www.holytrinityorthodox.com/ru/calendar/los/(.*?).htm"#i', function ($matches) {
            $key = $matches[1];
            $key = str_replace("/", "-", $key);
            $key = strtolower($key);
            return 'data-saint="' . $key . '"';
        }, $saints);

        $assignArray['date_o'] = strftime('%d %b %Y', $dateStampO);
        $assignArray['date'] = strftime('%d %b %Y', $dateStamp);
        $assignArray['day'] = strftime('%A', $dateStamp);
        $assignArray['bu_day'] = date('Y-m-d', $dateStamp);
        $assignArray['date_prev'] = $date_prev;
        $assignArray['date_next'] = $date_next;
        $assignArray['title'] = $week_title;
        $assignArray['glas'] = $glas;
        $assignArray['reading'] = $reading_str;
        $assignArray['lent'] = $fast;
        $assignArray['saints'] = $saints;
        $assignArray['prayers'] = $prayers;
        $assignArray['prokimen'] = $prokimen;
        $assignArray['aliluja'] = $aliluja;
        $assignArray['prichasten'] = $prichasten;
        if ($this->isDebug) {
            $assignArray['debug'] = $debug;
            $assignArray['debug_r'] = $debug_r;
        }


        $staticData = $this->getStaticData($dateStamp);
        if ($staticData) {
            if ($staticData['saints']) {
                // $assignArray['saints'] = $staticData['saints'];
            }
            $readings = '';
            foreach ($staticData['readings'] as $serviceType => $readingGroup) {
                $serviceType = $serviceType == 'Утр' ? 'Утреня' : $serviceType;
                $serviceType = $serviceType == 'Лит' ? 'Литургия' : $serviceType;
                $readings .= $serviceType . ": <ul>";
                foreach ($readingGroup as $readingType => $reading) {
                    // TODO: check if this needs to be urlencoded
                    $readingStr = join(' ', preg_replace('/<a\s+href="([^"]+)"\s*>/', '<a class="reading" href="http://bible.psmb.ru/bible/book/$1/">', $reading));
                    $readingType = $readingType == 'Рядовое' ? '' : $readingType . ": ";
                    $readings .= "<li>" . $readingType . str_replace('*', '', $readingStr) . "</li>";
                }
                $readings .= "</ul>";
            }
            if ($staticData['readings']) {
                $assignArray['reading'] = $readings;
            }

            $assignArray['comment'] = preg_replace('/<a\s+href="([^"]+)"\s*>/', '<a class="reading" href="http://bible.psmb.ru/bible/book/$1/">', $staticData['comment'] ?? '');
        }

        $jsonArray = array(
            "title" => $staticData['title'] ?? $assignArray['title'] ?? null,
            "readings" => $staticData['readings'] ?? $assignArray['reading'] ?? null,
            'bReadings' => $this->getBReadings($dateStamp),
            //"saints" => $staticData['saints'] ?? $assignArray['saints'] ?? null,
            "saints" => $assignArray['saints'] ?? null,
            "prayers" => $assignArray['prayers'] ?? null,
            "prokimen" => $assignArray['prokimen'] ?? null,
            "aliluja" => $assignArray['aliluja'] ?? null,
            "prichasten" => $assignArray['prichasten'] ?? null,
            "lent" => $assignArray['lent'] ?? null,
            "glas" => $assignArray['glas'] ?? null,
            "comment" => $assignArray['comment'] ?? null
        );

        return $jsonArray;
    }
}

$day = new Day;
$date = $_GET['date'] ?? null;
$data = $day->run($date);

$readings = $_GET['readings'] ?? null;
header('Content-Type: application/json');
if ($readings) {
    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($data['readings']));
    $result = [];
    foreach ($it as $complexVerse) {
        $bible = new Bible;
        if ($complexVerse) {
            $simpleVerses = explode('~', $complexVerse);
            foreach ($simpleVerses as $verse) {
                $result[$v] = $bible->run($simpleVerse, null);
            }
        }
    }
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    echo json_encode($data, JSON_PRETTY_PRINT);
}
