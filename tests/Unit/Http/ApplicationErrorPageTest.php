<?php

namespace Tests\Unit\Http;

use App\Support\Http\ApplicationErrorPage;
use Tests\TestCase;

class ApplicationErrorPageTest extends TestCase
{
    public function test_client_errors_are_branded_in_debug_and_production(): void
    {
        foreach ([403, 404, 419] as $status) {
            $this->assertTrue(ApplicationErrorPage::shouldRender($status, true));
            $this->assertTrue(ApplicationErrorPage::shouldRender($status, false));
        }
    }

    public function test_server_errors_are_branded_only_when_debug_is_off(): void
    {
        $this->assertFalse(ApplicationErrorPage::shouldRender(500, true));
        $this->assertTrue(ApplicationErrorPage::shouldRender(500, false));
        $this->assertFalse(ApplicationErrorPage::shouldRender(503, false));
        $this->assertFalse(ApplicationErrorPage::shouldRender(422, false));
    }
}
