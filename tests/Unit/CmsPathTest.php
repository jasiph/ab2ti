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
        $this->assertFalse(CmsPath::isReserved('about/team'));
        $this->assertFalse(CmsPath::isReserved('sermons'));
        $this->assertFalse(CmsPath::isReserved('events'));
    }

    public function test_catch_all_constraint_rejects_reserved_prefixes(): void
    {
        $pattern = '#^'.CmsPath::catchAllConstraint().'$#';

        $this->assertDoesNotMatchRegularExpression($pattern, 'login');
        $this->assertDoesNotMatchRegularExpression($pattern, 'admin/help');
        $this->assertDoesNotMatchRegularExpression($pattern, 'register');
        $this->assertMatchesRegularExpression($pattern, 'about');
        $this->assertMatchesRegularExpression($pattern, 'about/team');
        $this->assertMatchesRegularExpression($pattern, 'sermons');
    }
}
