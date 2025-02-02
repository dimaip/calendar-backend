<?php
setlocale(LC_ALL, 'ru_RU.utf8');

function endsWith($haystack, $needle)
{
	return substr($haystack, -strlen($needle)) === $needle;
}

function datediff($interval, $datefrom, $dateto, $using_timestamps = false)
{
	/*
	$interval can be:
	yyyy - Number of full years
	q - Number of full quarters
	m - Number of full months
	y - Difference between day numbers
		(eg 1st Jan 2004 is "1", the first day. 2nd Feb 2003 is "33". The datediff is "-32".)
	d - Number of full days
	w - Number of full weekdays
	ww - Number of full weeks
	h - Number of full hours
	n - Number of full minutes
	s - Number of full seconds (default)
	*/

	if (!$using_timestamps) {
		$datefrom = strtotime($datefrom, 0);
		$dateto = strtotime($dateto, 0);
	}
	$difference = $dateto - $datefrom; // Difference in seconds

	switch ($interval) {

		case 'yyyy': // Number of full years

			$years_difference = floor($difference / 31536000);
			if (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom), date("j", $datefrom), date("Y", $datefrom) + $years_difference) > $dateto) {
				$years_difference--;
			}
			if (mktime(date("H", $dateto), date("i", $dateto), date("s", $dateto), date("n", $dateto), date("j", $dateto), date("Y", $dateto) - ($years_difference + 1)) > $datefrom) {
				$years_difference++;
			}
			$datediff = $years_difference;
			break;

		case "q": // Number of full quarters

			$quarters_difference = floor($difference / 8035200);
			while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom) + ($quarters_difference * 3), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
				$months_difference++;
			}
			$quarters_difference--;
			$datediff = $quarters_difference;
			break;

		case "m": // Number of full months

			$months_difference = floor($difference / 2678400);
			while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom) + ($months_difference), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
				$months_difference++;
			}
			$months_difference--;
			$datediff = $months_difference;
			break;

		case 'y': // Difference between day numbers

			$datediff = date("z", $dateto) - date("z", $datefrom);
			break;

		case "d": // Number of full days

			$datediff = floor($difference / 86400);
			break;

		case "w": // Number of full weekdays

			$days_difference = floor($difference / 86400);
			$weeks_difference = floor($days_difference / 7); // Complete weeks
			$first_day = date("w", $datefrom);
			$days_remainder = floor($days_difference % 7);
			$odd_days = $first_day + $days_remainder; // Do we have a Saturday or Sunday in the remainder?
			if ($odd_days > 7) { // Sunday
				$days_remainder--;
			}
			if ($odd_days > 6) { // Saturday
				$days_remainder--;
			}
			$datediff = ($weeks_difference * 5) + $days_remainder;
			break;

		case "ww": // Number of full weeks

			$datediff = floor($difference / 604800);
			break;

		case "h": // Number of full hours

			$datediff = floor($difference / 3600);
			break;

		case "n": // Number of full minutes

			$datediff = floor($difference / 60);
			break;

		default: // Number of full seconds (default)

			$datediff = $difference;
			break;
	}

	return $datediff;
}

function easter($year)
{
	$a = $year % 19;
	$b = $year % 4;
	$c = $year % 7;
	$d = (19 * $a + 15) % 30;
	$e = (2 * $b + 4 * $c + 6 * $d + 6) % 7;

	if (($d + $e) > 10)
		$easter_o = ($d + $e - 9) . "-4-" . $year;
	else
		$easter_o = (22 + $d + $e) . "-3-" . $year;
	$easter_o_stamp = strtotime($easter_o);
	$easter_stamp = strtotime("+13 days", $easter_o_stamp);
	return $easter_stamp;
}

// Join data entries, specifiying how to join each key
function smartMerge($data = [], $htmlFields = [], $stringFields = [])
{
	$mergedData = [];
	foreach (array_merge($htmlFields, $stringFields) as $key) {
		$mergedData[$key] = '';
	}
	foreach ($data as $dataEntry) {
		foreach ($dataEntry as $key => $value) {
			if ($value) {
				if (in_array($key, $htmlFields)) {
					if ($mergedData[$key] && $value !== '#SR' && $value !== '#NSR') {
						$mergedData[$key] .= "<br/>";
					}
					$mergedData[$key] .= trim($value);
				} else if (in_array($key, $stringFields)) {
					if ($mergedData[$key]) {
						$mergedData[$key] .= ". ";
					}
					$mergedData[$key] .= trim($value);
				} else {
					if (!isset($mergedData[$key])) {
						$mergedData[$key] = [];
					}
					$mergedData[$key][] = $value;
				}
			}
		}
	}
	return $mergedData;
}
