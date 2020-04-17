<?php
require_once('init.php');

function arabic($roman)
{
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

function do_reg($text, $regex)
{
    preg_match_all($regex, $text, $result);
    return ($result['0']);
}

class Bible
{
    protected $activeTransName = null;

    protected function getFragments($title)
    {
        $text = '';
        $googleUrl = 'https://docs.google.com/spreadsheet/pub?hl=en&hl=en&key=0AnIrRiVoiOUSdENKckd0Vm1RbVhUMGVOQWNIZUNBUmc&single=true&output=csv&gid=';

        $filename = 'Data/cache_texts.csv';
        $gid = 134408876;
        // if ($this->isDebug) {
        //     unlink($filename);
        // }
        if (!file_exists($filename)) {
            file_put_contents($filename, file_get_contents($googleUrl . $gid));
        }
        $file = fopen($filename, 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            if ($line[0] === $title) {
                $text = $line[1];
            }
        }
        fclose($file);

        $verses = explode(PHP_EOL, $text);
        $verses = array_values(array_filter($verses, function ($i) {
            return strlen(trim($i)) > 0;
        }));
        $index = 0;
        $verses = array_map(function ($item) use (&$index) {
            $index++;
            return [
                'verse' => strval($index),
                'type' => 'regular',
                'text' => $item
            ];
        }, $verses);

        return [[
            'chapter' => '1',
            'verses' => $verses,
            'type' => 'regular'
        ]];
    }

    protected function availTrans($bookKey, $activeTrans = null)
    {
        $i = 0;
        $dir = scandir('bible');
        asort($dir);
        foreach ($dir as $folder) {
            if (!(($folder == '.') || ($folder == '..') || ($folder == '.git'))) {
                $settings = file("bible/" . $folder . "/bibleqt.ini");

                foreach ($settings as $key => $setting) {
                    $comm = preg_match('{^\s*//}', $setting);
                    if (!$comm) {
                        $bib = preg_match('{^\s*BibleName\s*=\s*(.+)$}', $setting, $matches);
                        if ($bib) {
                            $bi = trim($matches['1']);
                        }

                        $reg = '{^\s*ShortName\s*=.*(\s+' . $bookKey . '\s+).*$}';
                        $short_name = preg_match($reg, $setting);
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

    static function translationPrepare($translation, &$text)
    {
        switch ($translation) {
            case "ALL":
                //$text = preg_replace('/<p>([0-9]{1,3})/','<p><sup>$1</sup>', $text);
                $text = preg_replace('/<a.*?<\/a>/i', '', $text);
                $text = html_entity_decode($text);
                break;
            case "RST":
                $text = preg_replace('/Глава\s*([0-9]{1,3})/', 'Глава$1', $text);
                $text = preg_replace('/\s+[0-9]{1,6}/', '', $text);
                $text = preg_replace('/Глава([0-9]{1,3})/', 'Глава $1', $text);
                break;
            case "RBO2011":
                $text = preg_replace('/<sup>([0-9]{1,3})<\/sup>/', '<p>$1', $text);
                $text = preg_replace('/<sup>([0-9]{1,3})[-\x{2013}]([0-9]{1,3})<\/sup>/u', '<p>$1-$2', $text); // unicode minus
                break;
            case "NET":
                $text = str_replace("<br>\n", ' ', $text);
                $text = preg_replace('/<bqverse ([0-9]{1,3})>([0-9]{1,3})/', '<p>$1', $text);
                $text = preg_replace('/<bqchapter ([0-9]{1,3})>/', '<bqchapter $1>Chapter $1', $text);
                break;
        }
    }

    /**
     * @param string $text
     * @return string
     */
    static function RomanReplaceArabic($text)
    {
        // "XV," => "15:"
        $res = preg_replace_callback(
            '/([MDCLXVI]+),/',
            function ($matches) {
                return arabic($matches[1]) . ":";
            },
            $text
        );
        return $res;
    }

    /**
     * @param string $versePart - for example: "8:7-19", "8", "7(8)-12(13)", "8:7-9:2(3)"
     * @param integer $prevChapter - chapter from previous versePart
     * @return array
     *
     * [
     * 'chapter_begin' => 8,
     * 'verse_begin' => 7,
     * 'chapter_end' => 9,
     * 'verse_end' => 2,
     * 'verse_end_optional' => 3,
     * ]
     *
     */
    static function parseVersePart($versePart, $prevChapter)
    {
        $parts = array_pad(explode('-', $versePart, 2), 2, null); // "8:7-9:2(3)" => ["8:7","9:2(3)"]
        if (!$parts[1])
            $parts[1] = $parts[0]; // $parts[1] may be null for "8" or "6:8" => "8-8" or "6:8-6:8
        $result = [];
        foreach ($parts as $i => $p) {
            list($chapter, $verse) = array_pad(explode(':', $p, 2), -2, null);
            preg_match('/(\d+)(?:\((\d+)\))?/', $verse, $matches);
            $word = ($i == 0 ? "begin" : "end");
            switch ($i) {
                case 0: //begin
                    $result["chapter_$word"] = $chapter ?: $prevChapter;
                    break;
                case 1: //end
                    $result["chapter_$word"] = $chapter ?: $result["chapter_begin"];
                    break;
            }
            if (!isset($matches[1])) {
                throw new Exception("Invalid verse: $verse");
            }
            $result["verse_$word"] = $matches[1];
            if (isset($matches[2]))
                $result["verse_{$word}_optional"] = $matches[2];
        }
        return $result;
    }


    /**
     * @param string $zachalo
     * @param string $trans
     * @return string
     */
    public function run($zachalo = 'Притч. XV, 20 - XVI, 9.', $trans = null)
    {
        $zachalo = str_replace('–', '-', $zachalo);
        $zachalo = str_replace('—', '-', $zachalo);
        if (strpos($zachalo, '@') !== false) {
            $z = str_replace("@", "", $zachalo);
            return [
                'translationList' => [],
                'translationCurrent' => '',
                'bookName' => $z,
                'verseKey' => $z,
                'zachaloTitle' => $z,
                'bookKey' => $z,
                'chapCount' => 1,
                'fragments' => $this->getFragments($z)
            ];
        }
        //supported $zachalo:
        //spaces ignored
        //Притч. XV, 20 - XVI, 9.
        //Притч. 15:20-27,29.
        //Притч. 15:20(17)-27(29). // (..) extended optional
        //Притч. 15:20(22)-27(25).
        //Притч. 15:20-16:9.
        //Притч. 15:20(16)-16:9(11).

        /*Притч. 15:10(7)-21(20),24 :
            7-9 - optional
            10-20 - regular
            21-21 - regularNotOptional
            22-23 - hidden
            24 - regular
        */
        $versekey = $zachalo;

        $ver = $zachalo;

        $orig_ver = $ver;
        $ver = str_replace(' ', '', $ver); //remove spaces;
        $ver = str_replace(' ', '', $ver); //remove unicode spaces;
        $ver = self::RomanReplaceArabic($ver);
        $ver = preg_replace('/,.*зач.*?,/', '', $ver); //Евр. V, 11 - VI, 8.  Remove zach
        $ver = preg_replace('/(\.$)/', '', $ver); //Евр. V, 11 - VI, 8 remove last dot
        $ver = preg_replace('#(\d{1,3}\(\d{1,3}\)?),(\(\d{1,3}\)?\d{1,3})#u', '$1,$2', $ver); //7:37-51(52),(11)12-15 :  "51(52),(11)12"  => "51(52);(11)12"
        $ver = preg_replace('#(\d{1,3}-\d{1,3}\(\d{1,3}\)?)-(w{1,4})#u', '$1,$2', $ver); //VII, (36)37-51(52) - VIII,12 : 37-51(52) - VIII => 37-51(52); VIII ? what is it?
        $ver = str_replace(';', ',', $ver); // "11:24-26;32-12:2" => "11:24-26,32-12:2"
        $verse = explode('.', $ver); //Евр | V, 11 - VI, 8 split book from verse
        $bookCoord = array_pop($verse);
        $bookKey = implode('.', $verse);
        $bookKey = str_replace(' ', '', $bookKey);
        $v_parts = explode(',', $bookCoord); //V, 11 - VI, 8 split verse on parts(if multipart verse)
        $v_parts = array_values(array_filter($v_parts));
        $printChapterBegin = 1000;
        $printChapterEnd = 0;
        $chtenije = [];
        $prevChapter = null;
        foreach ($v_parts as $i => $v_part) { //II, 23 - III, 5
            $chtenije[$i] = self::parseVersePart($v_part, $prevChapter);
            $prevChapter = $chtenije[$i]['chapter_end'];
            $printChapterBegin = min($printChapterBegin, $chtenije[$i]['chapter_begin']);
            $printChapterEnd = max($printChapterEnd, $chtenije[$i]['chapter_end']);
        }

        $avail_trans = $this->availTrans($bookKey, $trans);

        $trans = $trans ? $trans : $avail_trans['0']['id'];
        $this->activeTransName = $this->activeTransName ? $this->activeTransName : $avail_trans['0']['name'];

        $settings = file("bible/" . $trans . "/bibleqt.ini");

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
                if ($fname) {
                    $fn = $matches['1'];
                }

                $reg = '{^\s*ShortName\s*=.*(\s+' . $bookKey . '\s+).*$}';
                $sn = preg_match($reg, $setting);
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
        $filepath = __DIR__ . '/bible/' . $trans . '/' . $path;
        $text = file_get_contents($filepath);

        self::translationPrepare('ALL', $text);
        self::translationPrepare(substr($trans, 1), $text);

        $chapters = explode($token, $text);
        foreach ($chapters as $i => $chapter) {
            $chapters[$i] = $token . $chapter;
        }

        $fragments = [];

        $chtenijeIdx = 0;
        $startPrintRegular = false;
        $startPrintHidden = false;
        $startPrintOptional = false;
        if (trim($printChapterBegin) > trim($printChapterEnd)) {
            throw new Exception("printChapterBegin ($printChapterBegin) is bigger than printChapterEnd($printChapterEnd)");
        }

        for ($chapIdx = trim($printChapterBegin); $chapIdx <= $printChapterEnd; $chapIdx++) {
            $chapIdxsChapterRegular = false;
            $chapter = [
                'chapter' => $chapIdx,
                'verses' => []
            ];

            $lines = explode("\n", $chapters[$chapIdx]);
            $prevVerseNo = false; // стихи бывают с ошибками, лучше подстраховаться, чтобы проверять корректность номеров
            foreach ($lines as $line) {

                if (substr($line, 0, 3) !== '<p>' || $line === '<p>') {
                    continue;
                }

                list($verseNo, $line) = explode(" ", substr($line, 3), 2); //$verseNo may be num or range 2-3
                list($verseNoBegin, $verseNoEnd) = array_pad(explode('-', $verseNo), 2, null);
                if (!$verseNoEnd)
                    $verseNoEnd = $verseNoBegin;

                //if ($prevVerseNo && ($verseNoBegin != $prevVerseNo + 1))
                //    trigger_error("Verse Number is wrong. Expected " . ($prevVerseNo + 1) . ", {$verseNoBegin} is given: {$trans}, {$chapIdx}:{$verseNo}."); //TODO send mail on error?

                $prevVerseNo = $verseNoEnd;

                if ($chtenije[$chtenijeIdx]['chapter_begin'] == $chapIdx && $chtenije[$chtenijeIdx]['verse_begin'] == $verseNo) {
                    $startPrintRegular = true;
                    $startPrintHidden = true;
                    $chapIdxsChapterRegular = true;
                    if (!isset($chtenije[$chtenijeIdx]['verse_begin_optional'])) {
                        $startPrintOptional = true;
                    }
                }

                if (
                    $chtenije[$chtenijeIdx]['chapter_begin'] == $chapIdx
                    && isset($chtenije[$chtenijeIdx]['verse_begin_optional'])
                    && $chtenije[$chtenijeIdx]['verse_begin_optional'] == $verseNo
                ) {
                    $startPrintOptional = true;
                }

                if ($startPrintRegular && $startPrintOptional) {
                    $verseType = 'regular';
                } elseif ($startPrintRegular && !$startPrintOptional) {
                    $verseType = 'regularNotOptional';
                } elseif (!$startPrintRegular && $startPrintOptional) {
                    $verseType = 'optional';
                } elseif ($startPrintHidden) {
                    $verseType = 'hidden';
                } else {
                    $verseType = false;
                }

                if ($verseType) {
                    $chapter['verses'][] = [
                        'verse' => $verseNo,
                        'type' => $verseType,
                        'text' => trim(strip_tags($line))
                    ];
                }

                if ($chtenije[$chtenijeIdx]['chapter_end'] == $chapIdx && $chtenije[$chtenijeIdx]['verse_end'] == $verseNo) {
                    $startPrintRegular = false;
                    $chapIdxsChapterRegular = true;
                    if (!isset($chtenije[$chtenijeIdx]['verse_end_optional']))
                        $startPrintOptional = false;
                }

                if (
                    $chtenije[$chtenijeIdx]['chapter_end'] == $chapIdx
                    && $verseNo == (isset($chtenije[$chtenijeIdx]['verse_end_optional'])
                        ? $chtenije[$chtenijeIdx]['verse_end_optional']
                        : $chtenije[$chtenijeIdx]['verse_end'])
                ) {
                    $chtenijeIdx++;
                    if (!isset($chtenije[$chtenijeIdx])) //last chtenie
                        break;
                }

                if (
                    $chtenije[$chtenijeIdx]['chapter_end'] == $chapIdx
                    && isset($chtenije[$chtenijeIdx]['verse_end_optional'])
                    && $chtenije[$chtenijeIdx]['verse_end_optional'] == $verseNo
                ) {
                    $startPrintOptional = false;
                }
            }

            $chapter['type'] = 'regular';

            $fragments[] = $chapter;
        }

        $jsonArray = [
            'translationList' => $avail_trans,
            'translationCurrent' => $trans,
            'bookName' => $full_name,
            'verseKey' => $versekey,
            'zachaloTitle' => $orig_ver,
            'bookKey' => $bookKey,
            'chapCount' => $chap_count,
            'fragments' => $fragments

        ];

        return $jsonArray;
    }
}
