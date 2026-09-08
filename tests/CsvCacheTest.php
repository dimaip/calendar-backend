<?php
require_once __DIR__ . '/../csvCache.php';
require_once __DIR__ . '/../day.php';

use PHPUnit\Framework\TestCase;

class CsvCacheTest extends TestCase
{
    private $directory;
    private $filename;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/csv-cache-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->filename = $this->directory . '/table.csv';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testEmptyCacheRecoversAfterFailedAttempt(): void
    {
        file_put_contents($this->filename, '');
        $attempts = 0;
        $rows = loadCsvCache($this->filename, 'unused', false, function () use (&$attempts) {
            return ++$attempts === 1 ? false : ",,\nMk23,Gospel\n";
        });
        $this->assertSame(2, $attempts);
        $this->assertSame([['Mk23', 'Gospel']], $rows);
        $this->assertSame($rows, loadCsvCache($this->filename, 'unused', false, function () {
            $this->fail('Valid cache must not download again');
        }));
    }

    public function testFailedRefreshPreservesGoodCache(): void
    {
        file_put_contents($this->filename, "207,Apostol\n");
        $this->assertSame([['207', 'Apostol']], loadCsvCache($this->filename, 'unused', true, function () {
            return '<html>Error, unavailable</html>';
        }));
        $this->assertSame("207,Apostol\n", file_get_contents($this->filename));
    }

    public function testFailureDoesNotCreateCache(): void
    {
        try {
            loadCsvCache($this->filename, 'unused', false, function () { return false; });
            $this->fail('Failed download must throw');
        } catch (RuntimeException $error) {
            $this->assertFileDoesNotExist($this->filename);
        }
    }

    public function testInvalidCsvIsRejected(): void
    {
        foreach (['', '<html>Error</html>', "Error\n", ",,,\n"] as $content) {
            $this->assertNull(parseCachedCsv($content));
        }
    }

    public function testMissingReferencesCannotSilentlyRemoveEitherReading(): void
    {
        $class = new ReflectionClass(Day::class);
        $method = $class->getMethod('processReadings');
        $method->setAccessible(true);
        foreach ([['207' => 'Apostol'], ['Mk23' => 'Gospel'], ['207' => 'Apostol', 'Mk23' => 'Gospel']] as $references) {
            $day = $class->newInstanceWithoutConstructor();
            foreach (['zachala' => $references, 'dayOfWeekNumber' => 3] as $key => $value) {
                $property = $class->getProperty($key);
                $property->setAccessible(true);
                $property->setValue($day, $value);
            }
            try {
                $result = $method->invoke($day, [['reading_title' => 'Рядовое', 'readings' => ['Литургия' => '207;Mk23']]]);
                $this->assertCount(2, $references);
                $this->assertSame(['Apostol', 'Gospel'], $result['Литургия']['Рядовое']);
            } catch (RuntimeException $error) {
                $this->assertCount(1, $references);
                $this->assertStringContainsString('Unresolved reading reference:', $error->getMessage());
            }
        }
    }
}
