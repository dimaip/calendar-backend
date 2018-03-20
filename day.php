<?php
	require('functions.php');

class Day
{
	protected $isDebug = false;
	protected $perehod;
	protected $neperehod;
	protected $sunday_matins_gospels;
	protected $zachala;
	protected $saints;
	protected $prazdnik_title;
	protected $skip_rjad;
	protected $no_liturgy;
	protected $day_of_week_number;

	protected $day_of_week_names = array('воскресение', 'понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу');

	protected function getStaticData($datestamp) {
		$d = date('Y-m-d', $datestamp);
		$filename = 'Data/processed/' . $d;
		if (file_exists($filename)) {
			return json_decode(file_get_contents($filename), true);
		} else {
			return null;
		}
	}

	protected function getDayAfter($date, $day_number = 1, $sh_times = 0, $no_jump_if_same_day = 0)
	{
		$day_stamp		  = strtotime($date);
		$current_day_number = date('N', $day_stamp);
		if ($current_day_number == 7)
			$current_day_number = 0;
		if ($current_day_number < $day_number) {
			$shift_to_day = $day_number - $current_day_number;
		} else if ($current_day_number == $day_number) {
			if ($no_jump_if_same_day == 0) {
				$shift_to_day = 7;
			}
		} else if ($current_day_number > $day_number) {
			$shift_to_day = 7 - $current_day_number + $day_number;
		}
		$day_after = strtotime('+' . $shift_to_day . ' day', $day_stamp);
		return $day_after;
	}
	protected function getDayBefore($date, $day_number = 1, $sh_times = 0)
	{
		$day_stamp = strtotime($date);
		$current_day_number = (int)date('N', $day_stamp);
		if ($current_day_number == 7) {
			$current_day_number = 0;
		}

		if ($day_number === 'w') {
			if ($current_day_number == 0) {
				$shift_to_day = 2;
			}
			else if ($current_day_number == 6) {
				$shift_to_day = 1;
			}
			else {
				$shift_to_day = 0;
			}
		} else {
			if ($current_day_number > $day_number) {
				$shift_to_day = $current_day_number - $day_number;
			} else if ($current_day_number == $day_number) {
				$shift_to_day = 7;
			} else if ($current_day_number < $day_number) {
				$shift_to_day = $current_day_number + 7 - $day_number;
			}
		}
		//additional shift of weeks
		$shift_to_day = $shift_to_day + $sh_times * 7;

		$day_after = strtotime('-' . $shift_to_day . ' day', $day_stamp);
		return $day_after;
	}

	protected function getDayNearest($date, $day_number = 1)
	{
		$day_stamp = strtotime($date);
		$dayBefore = $this->getDayBefore($date, $day_number);
		$dayAfter  = $this->getDayAfter($date, $day_number);
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
			$sh_date_o = str_replace("/", "-", $out['1']) . "-" . $d_Y; //date, OC, with slashes
			$sh_sign = $out['2'] ?? null; //operation sign
			$sh_dayn = $out['3'] ?? null; //day number,0 - sunday
			$sh_times = $out['4'] ?? null;
			$sh_date_stamp = strtotime('+13 days', strtotime($sh_date_o)); //timestamp NC
			$sh_date = date('d-m-Y', $sh_date_stamp); //NC date
			switch ($sh_sign) {
				case '+':
					$res_key = date('d/m', strtotime('-13 days', $this->getDayAfter($sh_date, $sh_dayn, $sh_times))); //OC, key
					break;
				case '-':
					$res_key = date('d/m', strtotime('-13 days', $this->getDayBefore($sh_date, $sh_dayn, $sh_times))); //OC, key
					break;
				case '~':
					$res_key = date('d/m', strtotime('-13 days', $this->getDayNearest($sh_date, $sh_dayn))); //OC, key
					break;
				case '':
					$res_key = date('d/m', strtotime($sh_date_o)); //OC, key
					break;
			}
			return $res_key;
		}
	}

	protected function getNeperehod($date_stamp)
	{
		$reading_array = [];
		$d_stamp = strtotime('-13days', $date_stamp); //date stamp, OC

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

	protected function process_perehods($week, $day_of_week_number, $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp)
	{
		$dayweek = $week . ';' . $day_of_week_number; //concat key
		//OVERLAY GOSPEL SHIFT
		$dayweek_gospelshift = ($week + $gospel_shift) . ';' . $day_of_week_number; //concat key
		$perehods = $this->perehod[$dayweek];
		$ap = explode(';', $perehods[0]['reading']['Литургия']);
		$ap = explode(';', $perehods[0]['reading']['Литургия']);
		$many_reads = $ap[2] ?? null;
		$ap = $ap[0];
		$gs = explode(';', $this->perehod[$dayweek_gospelshift][0]['reading']['Литургия']);
		$gs = $gs[1] ?? null;
		if (($ap || $gs) && !$many_reads) {
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
			//if($array['prazdnik_title'])
			//	$this->prazdnik_title .= $array['prazdnik_title'].'<br/>';
			foreach ($array['reading'] as $service_key => $readings) {
				//if(!$nr[$service_key][$reading_title])
				if ($readings)
					$nr[$service_key][$reading_title] = $readings;
				//else
				//	die("<span style='color:red'>Two readings with same title!</span>");
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
		if ((count($nr_or['Утреня']) > 1) && $nr_or['Утреня']['Воскресное евангелие']) {
			//unset sunday saint's matins?
		}

		$reading_str_res = '';
		foreach ($nr_or as $service_key => $nr2) {
			$fl = false;
			if ($this->no_liturgy && $service_key == 'Литургия')
			continue;
			$reading_str = $service_key . ": ";
			if ($nr2) {
				foreach ($nr2 as $rtitle => $readings) {
					if ($rtitle == 'Рядовое' && $this->skip_rjad && $service_key == 'Литургия')
						continue;
					$str  = "<li>" . $rtitle . ": ";
					$flag = false;
					foreach (explode(";", $readings) as $reading) {
						$reading_ex = explode("/", $reading);
						if ($weekend && $reading_ex[1])
							$reading = $reading_ex[1];
						else
							$reading = $reading_ex[0];
						if ($r = $this->zachala[$reading]) {
							$flag = true;
							$fl   = true;
							$str .= '&nbsp;<a class="reading" href="https://bible.psmb.ru/bible/book/' . $reading . '/">' . $r . '</a>&nbsp;&nbsp;';
						}
					}
					if ($flag)
						$reading_str .= "<ul>" . $str . "</ul>";
				}
			}
			if ($fl) {
				$reading_str_res .= $reading_str;
			}
		}
		$reading_str_res = str_replace('Рядовое: ', '', $reading_str_res);

		return $reading_str_res;
	}
	protected function check_skip_rjad($saints)
	{
		//skip rjadovoje reading if not sunday and Great prazdnik
		$skip_rjad = false;
		if ((strpos($saints, "#TP6") || strpos($saints, "#TP5")) && ($this->day_of_week_number != 0))
			$skip_rjad = true;
		//skip rjadovoe
		if (strpos($saints, "#SR") !== false)
			$skip_rjad = true;
		if (strpos($saints, "#NSR") !== false)
			$skip_rjad = false;
		return $skip_rjad;
	}

	protected function init($date)
	{
		if (!file_exists('Data')) {
			mkdir('Data', 0777, true);
		}
		$google_url = 'https://docs.google.com/spreadsheet/pub?hl=en&hl=en&key=0AnIrRiVoiOUSdENKckd0Vm1RbVhUMGVOQWNIZUNBUmc&single=true&output=csv&gid=';

		$filename = 'Data/cache_zachala_apostol.csv';
		$gid = 3;
		if ($this->isDebug) {
			unlink($filename);
		}
		if (!file_exists($filename)) {
			file_put_contents($filename, file_get_contents($google_url . $gid));
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
			file_put_contents($filename, file_get_contents($google_url . $gid));
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
			file_put_contents($filename, file_get_contents($google_url . $gid));
		}
		$file = fopen($filename, 'r');
		while (($line = fgetcsv($file)) !== FALSE) {
			$weekday = $line[0];
			unset($neperehod_array);
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
			$this->perehod[$weekday][] = $perehod_array;
		}
		fclose($file);

		$filename = 'Data/cache_neperehod.csv';
		$gid = 0;
		if ($this->isDebug) {
			unlink($filename);
		}
		if (!file_exists($filename)) {
			file_put_contents($filename, file_get_contents($google_url . $gid));
		}
		$file = fopen($filename, 'r');
		while (($line = fgetcsv($file)) !== FALSE) {
			$date = $line[0];
			$neperehod_array['week_title'] = $line[1];
			$neperehod_array['saints'] = $line[2];
			$neperehod_array['reading_title'] = $line[3];
			$neperehod_array['reading']['Утреня'] = $line[4];
			$neperehod_array['reading']['Литургия'] = $line[5];
			$neperehod_array['reading']['Вечерня'] = $line[6];
			$neperehod_array['reading']['1-й час'] = $line[7];
			$neperehod_array['reading']['3-й час'] = $line[8];
			$neperehod_array['reading']['6-й час'] = $line[9];
			$neperehod_array['reading']['9-й час'] = $line[10];
			$neperehod_array['reading']['На освящении воды'] = $line[11];
			$neperehod_array['prayers'] = $line[12];
			$this->neperehod[$date][] = $neperehod_array;
		}
		fclose($file);

		$file = fopen('Data/static_sunday_matins_gospels.csv', 'r');
		while (($line = fgetcsv($file)) !== FALSE) {
			$this->sunday_matins_gospels[$line[0]] = $line[1];
		}
		fclose($file);

		$filename = 'Data/cache_saints.csv';
		$gid = 5;
		if (!file_exists($filename)) {
			file_put_contents($filename, file_get_contents($google_url . $gid));
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
		$date_stamp_o = strtotime($date);
		$date_stamp = strtotime("+13 days", $date_stamp_o);
		$this->day_of_week_number = date('N', $date_stamp) % 7;

		$year = date("Y", $date_stamp);
		$easter_stamp = easter($year);

		if ($easter_stamp > $date_stamp) {
			$year = $year - 1;
			$easter_stamp = easter($year);
		}
		$next_easter_stamp = easter($year + 1);
		$debug .= "год" . $year;

		$week = datediff('ww', $easter_stamp, $date_stamp, true) + 1;

		$fast = null;
		if (strtotime('15-11-' . $year) <= $date_stamp_o && $date_stamp_o <= strtotime('24-12-' . ($year))) {
			$fast = "Рождественский (Филиппов) пост";
		}
		if (strtotime('01-08-' . $year) <= $date_stamp_o && $date_stamp_o <= strtotime('14-08-' . ($year))) {
			$fast = "Успенский пост";
		}
		if ((11 <= $week) && $date_stamp_o <= strtotime('29-06-' . ($year))) {
			$fast = "Петров пост";
		}


		$sunday_after_krest = $this->getDayAfter('27-09-' . $year, 0);
		$monday_after_sunday_after_krest = strtotime("+1 day", $sunday_after_krest);
		$week_after_krest = datediff('ww', $easter_stamp, $monday_after_sunday_after_krest, true) + 1;
		$krest_diff = 25 - $week_after_krest;
		$monday_18th_week = strtotime("+24 weeks 1 day", $easter_stamp);

		if ($date_stamp >= $monday_after_sunday_after_krest) { //in any case, start to read Luke only on Monday after Sunday after Krestovozdvijenije
			$gospel_shift = $krest_diff;
		} else if ($date_stamp >= $monday_18th_week) {
			$gospel_shift = -6 + $krest_diff; //shift back to Mt and skip Mk weeks
		} else {
			$gospel_shift = 0;
		}

		$prosv = strtotime('19-01-' . ($year + 1));
		$monday_after_prosv = $this->getDayAfter('19-01-' . ($year + 1), 1, 0, 1); //NB: Weird stuff, but if Prosv is on Monday, the reset should occur the same day
		$week_after_prosv = $this->getDayAfter('19-01-' . ($year + 1), 0);
		$week_to_easter	 = datediff('ww', $next_easter_stamp, $date_stamp, true) + 1;

		$week_old = $week; //save old week number, neccessary for matins order
		if ($week > 40 || $date_stamp >= $monday_after_prosv) {
			/*if (
			($week_to_easter == -12 && $this->day_of_week_number != 0)
			||
			($week_to_easter == -11 && $this->day_of_week_number == 0)
			) {
			$debug .= "week_to_easter-14!";
			//$week_to_easter = $week_to_easter - 14;
			} else if ($week_to_easter <= -12) {
			$debug .= "week_to_easter+0!";
			$week_to_easter = $week_to_easter;
			}*/
			$debug .= "week season!";
			$week = 50 + $week_to_easter;
		}
		if ($date_stamp >= $monday_after_prosv) {
			$debug .= "gospel shift reset!";
			$gospel_shift = 0;
		}
		$debug .= "Неделя" . $week;
		$debug .= "Неделя_old" . $week_old;
		$debug .= "Понедельник по крестовоздвижению: " . date("Ymd", $monday_after_sunday_after_krest) . "| 17-я неделя " . date('Ymd', $monday_18th_week) . "<br/>";
		$debug .= "Неделя по просвящении " . date('Ymd', $week_after_prosv);
		$debug .= "<br/>Сдвиг: крест" . $krest_diff . "|еванг" . $gospel_shift;
		$debug .= "<br/>Неделя по пасхе: " . $week;
		$debug .= "<br/>Недель до следующей пасхи: " . $week_to_easter;


		//matins sunday
		$matins_zachalo = null;
		if ($this->day_of_week_number == 0) {
			if ($week_old >= 9) {
				$matins_key = ($week_old - 9) % 11 + 1;
			} else if (1 < $week_old) {
				$matins_pre50 = explode(",", "1,3,4,7,8,10,9");
				$matins_key = $matins_pre50[$week_old - 2];
			}
			$matins_zachalo = $this->sunday_matins_gospels[$matins_key];
		}

		//glass
		$glas = (($week_old - 1) % 8);
		$glas = $glas ? $glas : 8;
		if (($week_old == 1) || ($week == 50) || ($week_old == 2))
			$glas = null;

		$perehods = $this->process_perehods($week, $this->day_of_week_number, $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp);



		if ($week + $gospel_shift == 36 && $this->day_of_week_number == 0) {
			$debug .= "gospel shifted from praotez";
			$praotec_stamp = strtotime(str_replace('/', '-', $this->getKey('25/12-0#1', $date_stamp_o) . "/" . $year));

			$ap = explode(";", $perehods[0]['reading']['Литургия']);
			$many_reads = $ap[2];
			$ap = $ap[0];
			$processed_perehod_for_praotez = $this->process_perehods(datediff('ww', $easter_stamp, $praotec_stamp, true) + 3, "0", $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp);
			$gs = explode(";", $processed_perehod_for_praotez[0]['reading']['Литургия']);
			$gs = $gs[1];
			if (($ap || $gs) && !$many_reads) {
				$perehods[0]['reading']['Литургия'] = $ap . ";" . $gs;
			}
		}
		if ($week == 37 && $this->day_of_week_number == 0) {
			$debug .= "apostol shifted from praotez";
			$praotec_stamp				 = strtotime(str_replace('/', '-', $this->getKey('25/12-0#1', $date_stamp_o) . "/" . $year));
			$processed_perehod_for_praotez = $this->process_perehods(datediff('ww', $easter_stamp, $praotec_stamp, true) + 3, "0", $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp);
			$ap = explode(";", $processed_perehod_for_praotez[0]['reading']['Литургия']);
			$ap = $ap[0];
			$gs = explode(";", $perehods[0]['reading']['Литургия']);
			$many_reads = $gs[2];
			$gs = $gs[1];
			if (($ap || $gs) && !$many_reads)
				$perehods[0]['reading']['Литургия'] = $ap . ";" . $gs;
		}



		//OVERLAY SUNDAY MATINS
		$mat['reading']['Утреня'] = $matins_zachalo;
		$mat['reading_title'] = 'Воскресное евангелие';
		$perehods[] = $mat;

		$neperehod_array = $this->getNeperehod($date_stamp);
		if (!$perehods)
			$arrayz = $neperehod_array;
		else if (!$neperehod_array)
			$arrayz = $perehods;
		else
			$arrayz = array_merge($perehods, $neperehod_array);

		$saints = '';
		$prayers = '';
		$week_title = '';
		//Saints
		foreach ($arrayz as $ar) {
			if (isset($ar['saints'])) {
				$saints .= $ar['saints'];
			}
			if (isset($ar['prayers'])) {
				$prayers .= $ar['prayers'];
			}
			if (isset($ar['week_title'])) {
				$week_title .= $ar['week_title'];
			}
		}

		if ($saints) {
			$saints .= "<br/>";
		}
		$saints .= $this->saints[date('d/m', $date_stamp_o)];


		$this->skip_rjad = $this->check_skip_rjad($saints);

		//PERENOS CHTENIJ
		if (($this->day_of_week_number != 0) && (!$this->skip_rjad)) { //not on Sunday, check for move forward
			$next_date_stamp_o = strtotime("+1 days", $date_stamp_o);
			$next_date_stamp   = strtotime("+1 days", $date_stamp);

			//combine saints
			$t = $this->getNeperehod($next_date_stamp);
			$next_saints = $t['0']['saints'];
			$r = $this->process_perehods($week, $this->day_of_week_number + 1, $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp);
			$next_saints .= $r['0']['saints'];
			$next_saints .= $this->saints[date('d/m', $next_date_stamp_o)];

			if ($this->check_skip_rjad($next_saints)) {
				$debug .= "<br>something holy is around";

				$r['0']['reading_title'] = 'За ' . $this->day_of_week_names[$this->day_of_week_number + 1];
				$arrayz = array_merge($arrayz, $r);
			}
		}
		//refactor at will
		//this must match http://c.psmb.ru/pravoslavnyi-kalendar/date/20130803?debug=1, also look at the previous days
		if (($this->day_of_week_number != 0) && (!$this->skip_rjad)) { //not on Sunday, check for move forward
			$next_date_stamp_o = strtotime("-1 days", $date_stamp_o);
			$next_date_stamp   = strtotime("-1 days", $date_stamp);

			//combine saints
			$t = $this->getNeperehod($next_date_stamp);
			$next_saints = $t['0']['saints'] ?? null;
			$r = $this->process_perehods($week, $this->day_of_week_number - 1, $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp);
			$next_saints .= $r['0']['saints'] ?? null;
			$next_saints .= $this->saints[date('d/m', $next_date_stamp_o)];

			if ($first_check = $this->check_skip_rjad($next_saints)) {
				$next_date_stamp_o = strtotime("-2 days", $date_stamp_o);
				$next_date_stamp = strtotime("-2 days", $date_stamp);

				//combine saints
				$t = $this->getNeperehod($next_date_stamp);
				$next_saints = $t['0']['saints'];
				$r2 = $this->process_perehods($week, $this->day_of_week_number - 2, $gospel_shift, $week_old, $date_stamp_o, $year, $easter_stamp);
				$next_saints .= $r2['0']['saints'];
				$next_saints .= $this->saints[date('d/m', $next_date_stamp_o)];
				if ($this->check_skip_rjad($next_saints) || ($this->day_of_week_number == 2)) {
					$debug .= "<br>something very holy is around!";

					$r['0']['reading_title'] = 'За ' . $this->day_of_week_names[$this->day_of_week_number - 1];
					$arrayz = array_merge($r, $arrayz);
				}
			}
		}



		if ($this->day_of_week_number == 0 && $week != 8) {
			require('Data/static_sunday_troparion.php');
			if ($prayers && $sunday_troparion[$glas])
				$prayers .= "<br/>";
			$prayers .= $sunday_troparion[$glas];
		}




		//skip rjad on sochelnik HACK HACK HACK 
		if ($this->day_of_week_number == 5 && ($date == $year . '1222'))
			$this->no_liturgy = true;
		$debug .= '<br/>пропуск рядового чтения:' . $this->skip_rjad;

		$saints = str_replace("#SR", "", $saints);
		$saints = str_replace("#NSR", "", $saints);
		$saints = preg_replace('/#TP(.)/', '<img src="/typo3conf/ext/orthodox/Resources/Public/Icons/$1.gif"/>', $saints);
		$saints = str_replace('1.gif"', '1.gif" title="Cовершается служба, не отмеченная в Типиконе никаким знаком"', $saints);
		$saints = str_replace('2.gif"', '2.gif" title="Совершается служба на шесть"', $saints);
		$saints = str_replace('3.gif"', '3.gif" title="Совершается служба со славословием"', $saints);
		$saints = str_replace('4.gif"', '4.gif" title="Совершается служба с полиелеем"', $saints);
		$saints = str_replace('5.gif"', '5.gif" title="Совершается всенощное бдение"', $saints);
		$saints = str_replace('6.gif"', '6.gif" title="Совершается служба великому празднику"', $saints);



		//format reading
		$weekend = false;
		if ($this->day_of_week_number == 0 || $this->day_of_week_number == 6) {
			$weekend = true;
		}
		$reading_str = $this->formatReading($arrayz, $weekend);


		//title
		if ($this->day_of_week_number == 0) {
			$sedmned = "Неделя";
			$week_old--;
		} else {
			$sedmned = "Седмица";
		}
		if (!$week_title) {
			if ($week_old == 1) {
				$week_title = "Светлая седмица";
			} else if ($week_old < 8) {
				if ($this->day_of_week_number == 0) {
					$week_old++;
				}
				$week_title = "$sedmned $week_old-я по Пасхе";
			} else if ($week > 43) {
				$week_title = "$sedmned " . ($week - 43) . "-я Великого поста";
			} else if ($week_old < 46) {
				$week_title = "$sedmned " . ($week_old - 7) . "-я по Пятидесятнице";
			}
		}

		$dayweek = ($week + $gospel_shift) . ";" . $this->day_of_week_number;

		$sermons = [];
		/*
		//sermons
		if ($this->isDebug) {
			$debug .= "<br><br>Код для вставки проповеди:<span style='color:red'>" . ($week + $gospel_shift) . ";" . $this->day_of_week_number . "</span>";
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query('tx_news_domain_model_news.uid as uid, tx_news_domain_model_news.title as title, teaser, author, author_email,  bodytext', 'tx_news_domain_model_news', 'tx_news_domain_model_news_category_mm', 'tx_news_domain_model_category', 'AND tx_news_domain_model_news.deleted=0 AND tx_news_domain_model_news.hidden=0 AND tx_news_domain_model_category.uid=24', '', 'datetime DESC');
		$today_date_slashy = date('d/m', $date_stamp_o);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$dd			  = $row['author_email'];
			$srm['uid']	  = $row['uid'];
			$srm['title']	= $row['title'];
			$srm['teaser']   = $row['teaser'];
			$srm['author']   = $row['author'];
			$srm['bodytext'] = $row['bodytext'];
			if (strstr($dd, ";")) {
				if ($dayweek == $dd) {
					$sermons[] = $srm;
				}
			} else {
				$dd = $this->getKey($dd, $date_stamp_o);
				if ($today_date_slashy == $dd) {
					$sermons[] = $srm;
				}
			}
		}
		*/

		$assignArray['date_o'] = strftime('%d %b %Y', $date_stamp_o);
		$assignArray['date'] = strftime('%d %b %Y', $date_stamp);
		$assignArray['day'] = strftime('%A', $date_stamp);
		$assignArray['bu_day'] = date('Y-m-d', $date_stamp);
		$assignArray['date_prev'] = $date_prev;
		$assignArray['date_next'] = $date_next;
		$assignArray['week'] = $week_title;
		$assignArray['glas'] = $glas;
		$assignArray['reading'] = $reading_str;
		$assignArray['lent'] = $fast;
		$assignArray['saints'] = $saints;
		$assignArray['prayers'] = $prayers;
		$assignArray['sermons'] = $sermons;
		if ($this->isDebug) {
			$assignArray['debug'] = $debug;
			$assignArray['debug_r'] = $debug_r;
		}


		$staticData = $this->getStaticData($date_stamp);
		if ($staticData) {
			if ($staticData['saints']) {
				// $assignArray['saints'] = $staticData['saints'];
			}
			foreach ($staticData['readings'] as $serviceType => $readingGroup) {
				$serviceType = $serviceType == 'Утр' ? 'Утреня' : $serviceType;
				$serviceType = $serviceType == 'Лит' ? 'Литургия' : $serviceType;
				$readings .= $serviceType . ": <ul>";
				foreach ($readingGroup as $readingType => $reading) {
					$readingStr = preg_replace('/<a\s+href="([^"]+)"\s*>/', '<a class="reading" href="http://bible.psmb.ru/bible/book/$1/">', $reading);
					$readingType = $readingType == 'Рядовое' ? '' : $readingType . ": ";
					$readings .= "<li>" . $readingType . str_replace('*', '', $readingStr) . "</li>";
				}
				$readings .= "</ul>";
			}
			if ($staticData['readings']) {
				$assignArray['reading'] = $readings;
			}

			if ($staticData['title']) {
				$assignArray['week'] = $staticData['title'];
			}
			$assignArray['comment'] = preg_replace('/<a\s+href="([^"]+)"\s*>/', '<a class="reading" href="http://bible.psmb.ru/bible/book/$1/">', $staticData['comment']);
		}

		$jsonArray = array(
			"title" => $staticData['title'] ?? $assignArray['title'] ?? null,
			"readings" => $staticData['readings'] ?? $assignArray['reading'] ?? null,
			"saints" => $staticData['saints'] ?? $assignArray['saints'] ?? null,
			"prayers" => $assignArray['prayers'] ?? null,
			"seromns" => $assignArray['sermons'] ?? null,
			"lent" => $assignArray['lent'] ?? null,
			"comment" => $assignArray['comment'] ?? null
		);

		return json_encode($jsonArray);
	}
}

$day = new Day;
echo $day->run();
