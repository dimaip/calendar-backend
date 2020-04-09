<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/TestIterator.php';

use PHPUnit\Framework\TestCase;

class BibleTest extends TestCase
{
    /**
     * @dataProvider readingProvider
     */
    public function testReadings($reading, $translation, $expected)
    {
        require_once __DIR__ . '/../bible.php';
        $bible = new Bible;
        $zachalo = $reading ?? null;
        if ($translation === 'default') {
            $translation = null;
        }

        $result = $bible->run($zachalo, $translation);
        $this->assertSame($expected, $result['fragments']);
    }

    public function readingProvider()
    {
        return new TestIterator(json_decode(file_get_contents(__DIR__ . "/BibleTest_readings.json"), true), ['reading', 'translation', 'fragments']);
    }

    /**
     * @dataProvider dayProvider
     */
    public function testDay($date, $expected)
    {
        require_once __DIR__ . '/../day.php';
        $day = new Day;
        $result = $day->run(str_replace('-', '', $date));
        $this->assertSame($expected, $result['readings']);
    }

    public function dayProvider()
    {
        return new TestIterator(json_decode(file_get_contents(__DIR__ . "/BibleTest_day.json"), true), ['date', 'readings']);
    }
}