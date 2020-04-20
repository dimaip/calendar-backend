<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/TestIterator.php';

use Spatie\Snapshots\MatchesSnapshots;

use PHPUnit\Framework\TestCase;

class BibleTest extends TestCase
{
    use MatchesSnapshots;

    /**
     * @dataProvider readingProvider
     */
    public function testReadings($reading, $translation)
    {
        require_once __DIR__ . '/../bible.php';
        $bible = new Bible;
        $bible->tryUseTestBibleFiles = true;
        $zachalo = $reading ?? null;
        if ($translation === 'default') {
            $translation = null;
        }

        try {
            $result = $bible->run($zachalo, $translation);
        } catch (Exception $e) {
            $this->assertMatchesTextSnapshot('Exception: ' . $e->getMessage());
            return;
        }

        $this->assertMatchesTextSnapshot(json_encode($result['fragments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function readingProvider()
    {
        return new TestIterator(json_decode(file_get_contents(__DIR__ . "/BibleTest_readings.json"), true), ['reading', 'translation']);
    }

    /**
     * @dataProvider dayProvider
     */
    public function testDay($date)
    {
        require_once __DIR__ . '/../day.php';
        $day = new Day;
        $result = $day->run(str_replace('-', '', $date));
        $this->assertMatchesTextSnapshot(json_encode($result['readings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function dayProvider()
    {
        return new TestIterator(json_decode(file_get_contents(__DIR__ . "/BibleTest_day.json"), true), ['date']);
    }
}