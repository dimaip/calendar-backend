<?php
// header('Access-Control-Allow-Origin: https://c.psmb.ru');

function arabic($roman) {
	$result = 0;
	// Remove subtractive notation.
	$roman = str_replace("CM", "DCCCC", $roman);
	$roman = str_replace("CD", "CCCC", $roman);
	$roman = str_replace("XC", "LXXXX", $roman);
	$roman = str_replace("XL", "XXXX", $roman);
	$roman = str_replace("IX", "VIIII", $roman);
	$roman = str_replace("IV", "IIII", $roman);
	// Calculate for each numeral.
	$result += substr_count($roman, 'M') * 1000;
	$result += substr_count($roman, 'D') * 500;
	$result += substr_count($roman, 'C') * 100;
	$result += substr_count($roman, 'L') * 50;
	$result += substr_count($roman, 'X') * 10;
	$result += substr_count($roman, 'V') * 5;
	$result += substr_count($roman, 'I');
	return $result;
}

function do_reg($text, $regex) {
	preg_match_all($regex, $text, $result);
	return($result['0']);
}
 
class Bible {
	protected $activeTransName = null;

	protected function availTrans($booKey, $activeTrans = null)
	{
		$i=0;
		$dir = scandir('bible');
		asort($dir);
		foreach ($dir as $folder) {
			if (!(($folder=='.') || ($folder=='..') || ($folder=='.git'))) {
				$settings = file("bible/" . $folder . "/bibleqt.ini");
				
				foreach ($settings as $key => $setting) {
					$comm = preg_match('{^\s*//}',$setting);
					if (!$comm) {		
						$bib = preg_match('{^\s*BibleName\s*=\s*(.+)$}',$setting,$matches);
						if ($bib) {$bi = trim($matches['1']);}
						
						$reg = '{^\s*ShortName\s*=.*(\s+'.$booKey.'\s+).*$}';
						$short_name = preg_match($reg,$setting);
						if ($short_name) {
							$avail_trans[$i]['id'] = trim($folder);
							$avail_trans[$i]['name'] = trim($bi);
							if ($activeTrans == $folder) {
								$this->activeTransName = $avail_trans[$i]['name'];
							}
							$i++;
							break;
						}
					}
				}
			}
			
		}
		return $avail_trans;
	}
	/**
	 * @param string $zachalo
	 * @param string $trans
	 * @return string
	 */
	public function run($zachalo = 'Притч. XV, 20 - XVI, 9.', $trans = null)
	{
		$versekey = $zachalo;

		$orig_ver = "";
		$ver = $zachalo;

		$orig_ver = $ver;
		$ver = preg_replace('/,.*зач.*?,/','',$ver); //Евр. V, 11 - VI, 8.  Remove zach 
		$ver = preg_replace('/(\.$)/','',$ver); //Евр. V, 11 - VI, 8 remove last dot
		//$ver = preg_replace('#(\d{1,3}),(\s\w{1,4})#u','$1;$2',$ver); //VII, 37-52, VIII,12 TEMPORARY DISABLED
		$ver = preg_replace('#(\d{1,3}),(\s\d{1,3})#u','$1;$2',$ver); //VII, 37-52, 12-15
		$ver = preg_replace('#(\d{1,3}-\d{1,3})\s-(\s\w{1,4})#u','$1;$2',$ver); //VII, 37-52 - VIII,12
		$verse = explode('.',$ver); //Евр | V, 11 - VI, 8 split book from verse
		$v_parts = explode(';',$verse['1']); //V, 11 - VI, 8 split verse on parts(if multipart verse)
		$i = 0;
		$print_b = 1000;
		$print_e = 0;
		foreach ($v_parts as $v_part) { //II, 23 - III, 5   
			$part_be = explode('-',$v_part); //II, 23 | III, 5 
			$part_b = explode(',',$part_be['0']); //II| 23 
			if (!$part_b['1']) { //hard to imagine this
				$part_b['1'] = $part_b['0']; 
				if ($saved_chap) {
					$part_b['0'] = $saved_chap; //Get previous chapter
				} else {
					return ("Этого дня в календаре нет!");
				}
			} else {
				$part_b['0'] = arabic($part_b['0']); //Convert chpter to arabic	//II		
			}
			if ($part_b['0']<$print_b) {
				$print_b = $part_b['0']; //Begining of reading chap
			}
			if ($part_b['0']>$print_e) {
				$print_e = $part_b['0']; //Ending of reading chap
			}
			$chtenije[$i]['chap_b'] = trim($part_b['0']);
			$saved_chap = $part_b['0'];
			$chtenije[$i]['stih_b'] = trim($part_b['1']);
			
			if (!$part_be['1']) {$part_be['1'] = $part_be['0'];} //just a single verse, set the ending to begining
			$part_e = explode(',',$part_be['1']);  //III, 5 FLAW
			if (!$part_e['1']) { //if doesn't span across few chapters
				$part_e['1'] = $part_e['0'];
				$part_e['0'] = $part_b['0'];
			} else {
				$part_e['0'] = arabic($part_e['0']); //Convert chpter to arabic	
			}
			$saved_chap = $part_e['0'];
			if ($part_e['0']<$print_b) {
				$print_b = $part_e['0'];
			}
			if ($part_e['0']>$print_e) {
				$print_e = $part_e['0'];
			}
			$chtenije[$i]['chap_e'] = trim($part_e['0']);
			$chtenije[$i]['stih_e'] = trim($part_e['1']);
			$i++;
		}
		
		$booKey = $verse['0'];
		$booKey = str_replace(' ','', $booKey);

		$avail_trans = $this->availTrans($booKey, $trans);

		$trans = $trans?$trans:$avail_trans['0']['id'];
		$this->activeTransName = $this->activeTransName?$this->activeTransName:$avail_trans['0']['name'];
		
		$settings = file("bible/".$trans."/bibleqt.ini");

		foreach ($settings as $key => $setting) {
			$comm = preg_match('{^\s*//}', $setting);
			if (!$comm) {			
				$chap = preg_match('{^\s*ChapterSign\s*=\s*(.+)$}', $setting, $matches);
				if ($chap) {
					$token = trim($matches['1']);
				}
				
				$path_name = preg_match('{^\s*PathName\s*=\s*(.+)$}', $setting, $matches);
				if ($path_name) {
					$pa = $matches['1'];
				}
				
				$fname = preg_match('{^\s*FullName\s*=\s*(.+)$}', $setting, $matches);
				if ($fname) {$fn = $matches['1'];}

				$reg = '{^\s*ShortName\s*=.*(\s+'.$booKey.'\s+).*$}';
				$sn = preg_match($reg,$setting);
				if ($sn) {
					$short_name = $sn;
				}

				$chap_cc = preg_match('{^\s*ChapterQty\s*=\s*(.+)$}', $setting, $matches);
				if ($chap_cc) {
					$chap_c = $matches['1'];
				}

				if (isset($short_name) && $chap_cc) {
					$path = trim($pa);
					$full_name = trim($fn);
					$chap_count = trim($chap_c);
					unset($short_name);
				}
			}
		}
		$filepath = 'bible/' . $trans . '/' . $path;
		$text = file_get_contents($filepath);
		$text = preg_replace('/<p>([0-9]{1,2})/','<p><sup>$1</sup>', $text);
		$text = preg_replace('/<a.*?<\/a>/i','', $text);
		
		if (substr($trans,1) == 'RBO2011') {
			$text = preg_replace('/<sup>/', '<p><sup>', $text);
		}
			
		$chapters = explode($token, $text);
		foreach ($chapters as $i => $chapter) {
			$chapters[$i] = $token . $chapter;
		}
		$output = '';
		$outputc = '';
		foreach ($chtenije as $int) {
			$chapters[$int['chap_b']] = str_replace('<p><sup>' . $int['stih_b'] . '</sup>', '</p><span class="quote"><p><sup>'.$int['stih_b'] . '</sup>', $chapters[$int['chap_b']]);
			$chapters[$int['chap_e']] = preg_replace('#(<p><sup>' . ($int['stih_e']) . '</sup>.*)#u','$1</p></span>', $chapters[$int['chap_e']]);
		}
		for($i = $print_b; $i <= $print_e; $i++) {
			$outputc .= $chapters[$i];
		}

		//
		if (substr($trans,1) == 'RST') {
			$outputc = preg_replace('/Глава\s*([0-9]{1,2})/','Глава$1',$outputc);
			$outputc = preg_replace('/\s+[0-9]{1,6}/','',$outputc);
			$outputc = preg_replace('/Глава([0-9]{1,2})/','Глава $1',$outputc);
		}
		//
		if ($zachalo) {
			preg_match_all('#<span class="quote">.*?</span>#us',$outputc,$matches);
			if (strlen($matches[0][0])<10) {
				//preg_match_all('#<span class="quote">.*#us',$outputc,$matches);
			}
			$outputc = implode('(...)<br/>',$matches['0']);
		}
		$output = $output.$outputc;

		$jsonArray = [
			'translationList' => $avail_trans,
			'translationCurrent' => $trans,
			'bookName' => $full_name,
			'verseKey' => $versekey,
			'zachaloTitle' => $orig_ver,
			'bookKey' => $booKey,
			'chapCount' => $chap_count,
			'output' => $output
			
		];

		return json_encode($jsonArray, JSON_PRETTY_PRINT);
	}
}

$bible = new Bible;
echo $bible->run();
