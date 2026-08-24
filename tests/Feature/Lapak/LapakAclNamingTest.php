<?php

namespace Tests\Feature\Lapak;

use Tests\TestCase;

class LapakAclNamingTest extends TestCase
{
    public function test_canonical_two_segment_lapak_permissions_are_registered(): void
    {
        $permissions = collect(config('acl.permissions'))->flatten()->unique()->values();

        $expected = [
            'lapak.view',
            'lapak.manage',
            'lapak.moderate',
        ];

        foreach ($expected as $name) {
            $this->assertTrue($permissions->contains($name), "Missing permission {$name}");
            $this->assertSame(1, substr_count($name, '.'));
        }

        foreach ($permissions as $name) {
            if (! str_starts_with((string) $name, 'lapak')) {
                continue;
            }

            $this->assertSame(1, substr_count((string) $name, '.'), (string) $name);
            $this->assertStringNotContainsString('.*', (string) $name);
        }

        $this->assertFalse($permissions->contains('lapak-storefronts.view'));
        $this->assertFalse($permissions->contains('lapak.listings.moderate'));
        $this->assertFalse($permissions->contains('lapak.*'));
    }
}
