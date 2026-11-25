<?php

namespace Tests\Unit;

use App\Support\Totp;
use Tests\TestCase;

class TotpTest extends TestCase
{
    public function test_a_current_code_verifies_and_a_wrong_code_does_not(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::at($secret, intdiv(time(), 30));

        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }
}
