<?php

namespace Tests\Unit\Editorial;

use App\Models\BlockTemplate;
use App\Support\Editorial\TemplateBlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateBlockRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_types_are_allowed_without_database_rows(): void
    {
        $registry = app(TemplateBlockRegistry::class);

        $this->assertTrue($registry->allows('Highlight Card (Android Style)'));
        $this->assertTrue($registry->allows('Beautiful Image with Caption'));
        $this->assertTrue($registry->allows('Two Columns Feature'));
        $this->assertTrue($registry->allows('Block Content'));
    }

    public function test_class_like_and_path_like_names_are_rejected(): void
    {
        $registry = app(TemplateBlockRegistry::class);

        $this->assertNull($registry->normalizeType('App\\Blocks\\EvilRenderer'));
        $this->assertNull($registry->normalizeType('../../etc/passwd'));
        $this->assertNull($registry->normalizeType('Foo::class'));
        $this->assertFalse($registry->allows('App\\Blocks\\EvilRenderer'));
    }

    public function test_active_block_template_name_is_allowed(): void
    {
        BlockTemplate::query()->create([
            'name' => 'Kartu Kutipan Staf',
            'html_content' => '<p>Kutipan</p>',
            'is_active' => true,
        ]);
        BlockTemplate::query()->create([
            'name' => 'Nonaktif',
            'html_content' => '<p>X</p>',
            'is_active' => false,
        ]);

        $registry = app(TemplateBlockRegistry::class);

        $this->assertTrue($registry->allows('Kartu Kutipan Staf'));
        $this->assertFalse($registry->allows('Nonaktif'));
        $this->assertFalse($registry->allows('Tidak Ada'));
    }
}
