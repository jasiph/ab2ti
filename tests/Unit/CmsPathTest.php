<?php

namespace Tests\Unit;

use App\Support\CmsPath;
use Tests\TestCase;

class CmsPathTest extends TestCase
{
    public function test_register_is_reserved_even_when_registration_is_disabled(): void
    {
        $this->assertTrue(CmsPath::isReserved('register'));
        $this->assertTrue(CmsPath::isReserved('register/now'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
    }

    public function test_nested_application_paths_are_reserved_by_first_segment(): void
    {
        $this->assertTrue(CmsPath::isReserved('admin/users'));
        $this->assertTrue(CmsPath::isReserved('/Login/'));
        $this->assertTrue(CmsPath::isReserved('keanggotaan'));
        $this->assertTrue(CmsPath::isReserved('keanggotaan/daftar'));
        $this->assertTrue(CmsPath::isReserved('anggota'));
        $this->assertTrue(CmsPath::isReserved('publikasi'));
        $this->assertTrue(CmsPath::isReserved('berita'));
        $this->assertTrue(CmsPath::isReserved('berita/contoh'));
        $this->assertTrue(CmsPath::isReserved('kegiatan'));
        $this->assertTrue(CmsPath::isReserved('siaran-pers'));
        $this->assertTrue(CmsPath::isReserved('liputan-media'));
        $this->assertTrue(CmsPath::isReserved('teknologi-tani'));
        $this->assertTrue(CmsPath::isReserved('teknologi-tani/contoh'));
        $this->assertTrue(CmsPath::isReserved('koperasi'));
        $this->assertTrue(CmsPath::isReserved('koperasi/info'));
        $this->assertTrue(CmsPath::isReserved('lapak'));
        $this->assertTrue(CmsPath::isReserved('lapak/contoh'));
        $this->assertTrue(CmsPath::isReserved('akun'));
        $this->assertTrue(CmsPath::isReserved('akun/lapak'));
        $this->assertTrue(CmsPath::isReserved('sermons'));
        $this->assertTrue(CmsPath::isReserved('events'));
        $this->assertTrue(CmsPath::isReserved('kontak'));
        $this->assertFalse(CmsPath::isReserved('about/team'));
    }

    public function test_catch_all_constraint_rejects_reserved_prefixes(): void
    {
        $pattern = '#^'.CmsPath::catchAllConstraint().'$#';

        $this->assertDoesNotMatchRegularExpression($pattern, 'login');
        $this->assertDoesNotMatchRegularExpression($pattern, 'admin/help');
        $this->assertDoesNotMatchRegularExpression($pattern, 'register');
        $this->assertDoesNotMatchRegularExpression($pattern, 'keanggotaan');
        $this->assertDoesNotMatchRegularExpression($pattern, 'keanggotaan/daftar');
        $this->assertDoesNotMatchRegularExpression($pattern, 'publikasi');
        $this->assertDoesNotMatchRegularExpression($pattern, 'berita');
        $this->assertDoesNotMatchRegularExpression($pattern, 'berita/contoh');
        $this->assertDoesNotMatchRegularExpression($pattern, 'kegiatan');
        $this->assertDoesNotMatchRegularExpression($pattern, 'siaran-pers');
        $this->assertDoesNotMatchRegularExpression($pattern, 'liputan-media');
        $this->assertDoesNotMatchRegularExpression($pattern, 'teknologi-tani');
        $this->assertDoesNotMatchRegularExpression($pattern, 'teknologi-tani/contoh');
        $this->assertDoesNotMatchRegularExpression($pattern, 'lapak');
        $this->assertDoesNotMatchRegularExpression($pattern, 'lapak/contoh');
        $this->assertDoesNotMatchRegularExpression($pattern, 'akun');
        $this->assertDoesNotMatchRegularExpression($pattern, 'akun/lapak');
        $this->assertDoesNotMatchRegularExpression($pattern, 'sermons');
        $this->assertDoesNotMatchRegularExpression($pattern, 'events');
        $this->assertDoesNotMatchRegularExpression($pattern, 'kontak');
        $this->assertMatchesRegularExpression($pattern, 'about');
        $this->assertMatchesRegularExpression($pattern, 'about/team');
        $this->assertMatchesRegularExpression($pattern, 'contact');
    }
}
