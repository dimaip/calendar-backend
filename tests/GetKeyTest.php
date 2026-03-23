<?php

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../day.php';

use PHPUnit\Framework\TestCase;

/**
 * Expose protected getKey for testing
 */
class TestableDay extends Day
{
    public function testGetKey($key, $d_stamp, $week = null, $dayOfWeekNumber = null)
    {
        return $this->getKey($key, $d_stamp, $week, $dayOfWeekNumber);
    }
}

class GetKeyTest extends TestCase
{
    private $day;

    protected function setUp(): void
    {
        $this->day = new TestableDay();
    }

    // === Existing format tests (regression) ===

    public function testExactDate()
    {
        // "25/12" = Dec 25, no operator
        $d_stamp = strtotime('25-12-2025'); // OC date
        $result = $this->day->testGetKey('25/12', $d_stamp);
        $this->assertEquals('25/12', $result);
    }

    public function testExactDateNoMatch()
    {
        // Key is 25/12 but we're checking against Dec 26
        $d_stamp = strtotime('26-12-2025');
        $result = $this->day->testGetKey('25/12', $d_stamp);
        $this->assertEquals('25/12', $result); // getKey returns the resolved date, caller checks match
    }

    public function testDayOfWeekConditionMatch()
    {
        // "25/12=0" = Dec 25 only if it's Sunday
        // Find a year where Dec 25 OC (= Jan 7 NC) falls on Sunday
        // Jan 7 2029 is Sunday (OC Dec 25 2028)
        $d_stamp = strtotime('25-12-2028'); // OC
        $shDateStamp = strtotime('+13 days', $d_stamp); // NC: Jan 7 2029
        $dow = date('w', $shDateStamp); // Should be 0 (Sunday)
        $result = $this->day->testGetKey('25/12=0', $d_stamp);
        if ($dow === '0') {
            $this->assertEquals('25/12', $result);
        } else {
            $this->assertNull($result);
        }
    }

    public function testDayOfWeekConditionNoMatch()
    {
        // "25/12=0" = Dec 25 only if Sunday
        // Jan 7 2026 is Wednesday (OC Dec 25 2025)
        $d_stamp = strtotime('25-12-2025');
        $shDateStamp = strtotime('+13 days', $d_stamp); // NC: Jan 7 2026
        $dow = date('w', $shDateStamp);
        if ($dow !== '0') {
            $result = $this->day->testGetKey('25/12=0', $d_stamp);
            $this->assertNull($result);
        } else {
            $this->markTestSkipped('Dec 25 OC 2025 happens to be Sunday');
        }
    }

    public function testNegatedDayOfWeek()
    {
        // "25/12=!0" = Dec 25 only if NOT Sunday
        $d_stamp = strtotime('25-12-2025');
        $shDateStamp = strtotime('+13 days', $d_stamp);
        $dow = date('w', $shDateStamp);
        $result = $this->day->testGetKey('25/12=!0', $d_stamp);
        if ($dow !== '0') {
            $this->assertEquals('25/12', $result);
        } else {
            $this->assertNull($result);
        }
    }

    public function testWeekdayCondition()
    {
        // "25/12=w" = Dec 25 only if it's a weekday
        $d_stamp = strtotime('25-12-2025');
        $shDateStamp = strtotime('+13 days', $d_stamp);
        $dow = date('w', $shDateStamp);
        $result = $this->day->testGetKey('25/12=w', $d_stamp);
        if ($dow !== '0' && $dow !== '6') {
            $this->assertEquals('25/12', $result);
        } else {
            $this->assertNull($result);
        }
    }

    public function testDayAfterOperator()
    {
        // "25/12+0" = first Sunday after Dec 25 OC
        $d_stamp = strtotime('25-12-2025');
        $result = $this->day->testGetKey('25/12+0', $d_stamp);
        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/\d\d\/\d\d/', $result);
    }

    public function testDayBeforeOperator()
    {
        // "06/01-0" = last Sunday before Jan 6 OC
        $d_stamp = strtotime('06-01-2026');
        $result = $this->day->testGetKey('06/01-0', $d_stamp);
        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/\d\d\/\d\d/', $result);
    }

    // === New perehod condition tests ===

    public function testPerehodConditionMatch()
    {
        // "15/04=49;0" = Apr 15 OC only when week=49, dayOfWeek=0
        $d_stamp = strtotime('15-04-2025');
        $result = $this->day->testGetKey('15/04=49;0', $d_stamp, 49, 0);
        $this->assertEquals('15/04', $result);
    }

    public function testPerehodConditionWrongWeek()
    {
        // "15/04=49;0" but actual week is 48
        $d_stamp = strtotime('15-04-2025');
        $result = $this->day->testGetKey('15/04=49;0', $d_stamp, 48, 0);
        $this->assertNull($result);
    }

    public function testPerehodConditionWrongDay()
    {
        // "15/04=49;0" but actual day is 3 (Wednesday)
        $d_stamp = strtotime('15-04-2025');
        $result = $this->day->testGetKey('15/04=49;0', $d_stamp, 49, 3);
        $this->assertNull($result);
    }

    public function testPerehodConditionNullWeek()
    {
        // When week context is not available (null), perehod condition should not match
        $d_stamp = strtotime('15-04-2025');
        $result = $this->day->testGetKey('15/04=49;0', $d_stamp, null, null);
        $this->assertNull($result);
    }

    public function testPerehodConditionNegated()
    {
        // "15/04=!49;0" = Apr 15 OC only when NOT (week=49, day=0)
        $d_stamp = strtotime('15-04-2025');

        // When it IS week 49 day 0 → should NOT match
        $result = $this->day->testGetKey('15/04=!49;0', $d_stamp, 49, 0);
        $this->assertNull($result);

        // When it's a different week → should match
        $result = $this->day->testGetKey('15/04=!49;0', $d_stamp, 48, 0);
        $this->assertEquals('15/04', $result);

        // When it's same week but different day → should match
        $result = $this->day->testGetKey('15/04=!49;0', $d_stamp, 49, 3);
        $this->assertEquals('15/04', $result);
    }

    public function testPerehodConditionWithVariousWeeks()
    {
        $d_stamp = strtotime('15-04-2025');

        // Week 1, day 0
        $result = $this->day->testGetKey('15/04=1;0', $d_stamp, 1, 0);
        $this->assertEquals('15/04', $result);

        // Week 50, day 6
        $result = $this->day->testGetKey('15/04=50;6', $d_stamp, 50, 6);
        $this->assertEquals('15/04', $result);

        // String vs int comparison: week passed as string
        $result = $this->day->testGetKey('15/04=49;0', $d_stamp, '49', '0');
        $this->assertEquals('15/04', $result);
    }

    public function testPerehodConditionCombinedWithDayAfter()
    {
        // "25/12+0" uses the + operator, not =, so week/day params are irrelevant
        $d_stamp = strtotime('25-12-2025');
        $resultWithoutContext = $this->day->testGetKey('25/12+0', $d_stamp);
        $resultWithContext = $this->day->testGetKey('25/12+0', $d_stamp, 49, 0);
        $this->assertEquals($resultWithoutContext, $resultWithContext);
    }

    public function testExistingDayOfWeekConditionUnaffectedByNewParams()
    {
        // "25/12=0" should still work the same even when week/day are passed
        $d_stamp = strtotime('25-12-2025');
        $resultWithout = $this->day->testGetKey('25/12=0', $d_stamp);
        $resultWith = $this->day->testGetKey('25/12=0', $d_stamp, 49, 0);
        $this->assertEquals($resultWithout, $resultWith);
    }
}
