<?php

namespace Tests\Feature\Membership;

use Tests\TestCase;

class MembershipAclNamingTest extends TestCase
{
    public function test_canonical_two_segment_membership_permissions_are_registered(): void
    {
        $permissions = collect(config('acl.permissions'))->flatten()->unique()->values();

        $expected = [
            'membership-applications.view',
            'membership-applications.review',
            'membership-applications.approve',
            'membership-applications.reject',
            'membership-applications.request-revision',
            'membership-members.view',
            'membership-members.update',
            'membership-documents.view',
            'membership-documents.manage',
        ];

        foreach ($expected as $name) {
            $this->assertTrue($permissions->contains($name), "Missing permission {$name}");
            $this->assertSame(1, substr_count($name, '.'));
        }

        foreach ($permissions as $name) {
            if (! str_starts_with((string) $name, 'membership')) {
                continue;
            }

            $this->assertSame(1, substr_count((string) $name, '.'), (string) $name);
            $this->assertDoesNotMatchRegularExpression('/^membership\.[a-z]/', (string) $name);
            $this->assertStringNotContainsString('.*', (string) $name);
            $this->assertStringNotContainsString('.workflow.', (string) $name);
        }

        $this->assertFalse($permissions->contains('membership.applications.approve'));
        $this->assertFalse($permissions->contains('membership.members.view'));
        $this->assertFalse($permissions->contains('membership.documents.view'));
        $this->assertFalse($permissions->contains('membership-applications.*'));
    }
}
