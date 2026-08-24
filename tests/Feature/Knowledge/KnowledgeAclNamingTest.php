<?php

namespace Tests\Feature\Knowledge;

use Tests\TestCase;

class KnowledgeAclNamingTest extends TestCase
{
    public function test_canonical_two_segment_knowledge_permissions_are_registered(): void
    {
        $permissions = collect(config('acl.permissions'))->flatten()->unique()->values();

        $expected = [
            'knowledge-articles.view',
            'knowledge-articles.create',
            'knowledge-articles.update',
            'knowledge-articles.publish',
            'knowledge-articles.delete',
        ];

        foreach ($expected as $name) {
            $this->assertTrue($permissions->contains($name), "Missing permission {$name}");
            $this->assertSame(1, substr_count($name, '.'));
        }

        foreach ($permissions as $name) {
            if (! str_starts_with((string) $name, 'knowledge')) {
                continue;
            }

            $this->assertSame(1, substr_count((string) $name, '.'), (string) $name);
            $this->assertDoesNotMatchRegularExpression('/^knowledge\.[a-z]/', (string) $name);
            $this->assertStringNotContainsString('.*', (string) $name);
        }

        $this->assertFalse($permissions->contains('knowledge.articles.view'));
        $this->assertFalse($permissions->contains('knowledge.articles.publish'));
        $this->assertFalse($permissions->contains('knowledge-articles.workflow.publish'));
        $this->assertFalse($permissions->contains('knowledge-articles.*'));
    }
}
