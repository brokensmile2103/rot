<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MoneyCompactTest extends TestCase
{
    public function test_rut_gon_tien_theo_nghin_va_trieu(): void
    {
        $this->assertSame('0', money_compact(0));
        $this->assertSame('500', money_compact(499.6));
        $this->assertSame('1k', money_compact(1000));
        $this->assertSame('125k', money_compact(125000));
        $this->assertSame('999k', money_compact(999499));
        $this->assertSame('1tr', money_compact(999500));
        $this->assertSame('1tr', money_compact(1000000));
        $this->assertSame('1,3tr', money_compact(1250000));
        $this->assertSame('12tr', money_compact(12000000));
        $this->assertSame('1.200tr', money_compact(1200000000));
    }

    public function test_so_am_va_chuoi_so(): void
    {
        $this->assertSame('-125k', money_compact(-125000));
        $this->assertSame('125k', money_compact('125000.40'));
    }
}
