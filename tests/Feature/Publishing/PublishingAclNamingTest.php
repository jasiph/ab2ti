<?php

namespace Tests\Feature\Publishing;

use Tests\TestCase;

class PublishingAclNamingTest extends TestCase
{
    public function test_canonical_two_segment_publishing_permissions_are_registered(): void
    {
        $permissions = collect(config('acl.permissions'))->flatten()->unique()->values();

        $expected = [
            'publishing-publications.view',
            'publishing-publications.create',
            'publishing-publications.update',
            'publishing-publications.publish',
            'publishing-publications.delete',
        ];

        foreach ($expected as $name) {
            $this->assertTrue($permissions->contains($name), "Missing permission {$name}");
            $this->assertSame(1, substr_count($name, '.'));
        }

        foreach ($permissions as $name) {
            if (! str_starts_with((string) $name, 'publishing')) {
                continue;
            }

            $this->assertSame(1, substr_count((string) $name, '.'), (string) $name);
            $this->assertDoesNotMatchRegularExpression('/^publishing\.[a-z]/', (string) $name);
            $this->assertStringNotContainsString('.*', (string) $name);
        }

        $this->assertFalse($permissions->contains('publishing.publications.publish'));
        $this->assertFalse($permissions->contains('publishing.publications.create'));
        $this->assertFalse($permissions->contains('publication.admin.edit'));
        $this->assertFalse($permissions->contains('publishing-publications.*'));
    }
}
