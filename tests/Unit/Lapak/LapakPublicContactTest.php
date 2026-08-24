<?php

namespace Tests\Unit\Lapak;

use App\Support\Lapak\LapakPublicContact;
use Tests\TestCase;

class LapakPublicContactTest extends TestCase
{
    public function test_whatsapp_normalizes_local_numbers_and_rejects_unsafe_input(): void
    {
        $this->assertSame(
            'https://wa.me/6281234567890?text='.rawurlencode('Halo'),
            LapakPublicContact::whatsappHref('081234567890', 'Halo'),
        );
        $this->assertSame(
            'https://wa.me/6281234567890',
            LapakPublicContact::whatsappHref('81234567890'),
        );
        $this->assertSame(
            'https://wa.me/6281234567890',
            LapakPublicContact::whatsappHref('+62 812-3456-7890'),
        );
        $this->assertNull(LapakPublicContact::whatsappHref('javascript:alert(1)'));
        $this->assertNull(LapakPublicContact::whatsappHref('data:text/html,hi'));
        $this->assertNull(LapakPublicContact::whatsappHref('https://evil.example/phish'));
        $this->assertNull(LapakPublicContact::whatsappHref('not-a-number'));
        $this->assertNull(LapakPublicContact::whatsappHref('+++'));
        $this->assertNull(LapakPublicContact::whatsappHref("  \t  "));
        $this->assertNull(LapakPublicContact::whatsappHref(str_repeat('a', 500)));
        $this->assertSame(
            'https://wa.me/6281234567890',
            LapakPublicContact::whatsappHref('  0812-3456-7890  '),
        );
    }

    public function test_tel_and_mailto_are_generated_from_structured_fields_only(): void
    {
        $this->assertSame('tel:+6281234567890', LapakPublicContact::telHref('0812-3456-7890'));
        $this->assertNull(LapakPublicContact::telHref('javascript:alert(1)'));
        $this->assertNull(LapakPublicContact::telHref("0812\x00alert"));
        $this->assertSame('mailto:penjual@example.com', LapakPublicContact::mailtoHref('  penjual@example.com  '));
        $this->assertNull(LapakPublicContact::mailtoHref('not-an-email'));
        $this->assertNull(LapakPublicContact::mailtoHref('penjual@example.com<script>'));
        $this->assertNull(LapakPublicContact::mailtoHref('javascript:alert(1)@example.com'));
    }
}
