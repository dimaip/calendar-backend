<?php
require_once __DIR__.'/../init.php';
require_once __DIR__.'/../bible.php';

use PHPUnit\Framework\TestCase;

class BibleTest extends TestCase
{
    public function testReadings()
    {
        $data = json_decode(file_get_contents(__DIR__."/BibleTest.json"), true);
        foreach ($data as $pair) {
            $bible = new Bible;
            $zachalo = $pair['reading']?? null;
            $translation = $pair['translation'] ?? null;
            if ($translation === 'default') {
                $translation = null;
            }
            if ($zachalo) {
                $result = $bible->run($zachalo, $translation);
                $this->assertSame($pair['fragments'], $result['fragments']);
            }
        }
    }
}