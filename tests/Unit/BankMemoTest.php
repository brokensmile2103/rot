<?php

namespace Tests\Unit;

use App\Models\Location;
use PHPUnit\Framework\TestCase;

class BankMemoTest extends TestCase
{
    public function test_unset_memo_falls_back_to_the_original_default(): void
    {
        $this->assertSame('Thanh toan don hang', (new Location())->bankMemo());
    }

    public function test_empty_memo_stays_empty(): void
    {
        $location = new Location();
        $location->setRawAttributes(['bank_memo' => null]);

        $this->assertSame('', $location->bankMemo());
    }

    public function test_memo_is_stripped_of_accents_and_symbols(): void
    {
        $this->assertSame('Ca phe Rot', Location::cleanMemo('Cà phê Rót!!'));
    }
}
