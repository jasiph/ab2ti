<?php

namespace Tests\Feature\Knowledge;

use App\Enums\KnowledgeArticleStatus;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class KnowledgeWorkflowTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_actor_can_create_a_draft(): void
    {
        $staff = $this->editor();

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Pengolahan benih padi',
                'excerpt' => 'Ringkasan singkat.',
                'content' => '<p>Isi artikel.</p>',
                'status' => 'published',
                'published_at' => '2020-01-01 00:00:00',
                'created_by' => User::factory()->create()->id,
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::query()->first();

        $this->assertNotNull($article);
        $this->assertSame(KnowledgeArticleStatus::Draft, $article->status);
        $this->assertSame('pengolahan-benih-padi', $article->slug);
        $this->assertNull($article->published_at);
        $this->assertSame($staff->id, $article->created_by);
        $this->assertSame($staff->id, $article->updated_by);
        $this->assertSame('<p>Isi artikel.</p>', $article->content);
    }

    public function test_draft_can_be_updated_without_changing_status(): void
    {
        $staff = $this->editor();
        $article = KnowledgeArticle::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Judul lama',
            'slug' => 'judul-lama',
        ]);

        $this->actingAs($staff)
            ->put(route('admin.knowledge.articles.update', $article), [
                'title' => 'Judul baru',
                'slug' => 'judul-baru',
                'excerpt' => 'Ringkasan baru',
                'content' => '<p>Isi baru</p>',
                'status' => 'published',
                'published_at' => '2019-05-05 12:00:00',
            ])
            ->assertRedirect(route('admin.knowledge.articles.edit', $article));

        $article->refresh();
        $this->assertSame(KnowledgeArticleStatus::Draft, $article->status);
        $this->assertSame('Judul baru', $article->title);
        $this->assertNull($article->published_at);
        $this->assertSame($staff->id, $article->updated_by);
    }

    public function test_valid_draft_can_be_published_with_system_timestamp(): void
    {
        Carbon::setTestNow('2026-08-20 10:15:00');

        $staff = $this->editor();
        $article = KnowledgeArticle::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Siap terbit',
            'slug' => 'siap-terbit',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.publish', $article), [
                'published_at' => '2018-01-01 00:00:00',
            ])
            ->assertRedirect(route('admin.knowledge.articles.edit', $article));

        $article->refresh();
        $this->assertSame(KnowledgeArticleStatus::Published, $article->status);
        $this->assertTrue($article->published_at->equalTo(Carbon::parse('2026-08-20 10:15:00')));
        $this->assertSame($staff->id, $article->updated_by);
    }

    public function test_publish_is_rejected_when_title_is_missing(): void
    {
        $staff = $this->editor();
        $article = KnowledgeArticle::factory()->create([
            'created_by' => $staff->id,
            'title' => 'Ada judul',
            'slug' => 'ada-judul',
        ]);
        $article->forceFill(['title' => ''])->save();

        $this->from(route('admin.knowledge.articles.edit', $article))
            ->actingAs($staff)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertRedirect(route('admin.knowledge.articles.edit', $article))
            ->assertSessionHas('error');

        $this->assertSame('draft', $article->fresh()->status->value);
        $this->assertNull($article->fresh()->published_at);
    }

    public function test_unpublish_returns_to_draft_and_keeps_published_at(): void
    {
        $staff = $this->editor();
        $publishedAt = Carbon::parse('2026-07-01 08:00:00');
        $article = KnowledgeArticle::factory()->published()->create([
            'created_by' => $staff->id,
            'published_at' => $publishedAt,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.unpublish', $article))
            ->assertRedirect(route('admin.knowledge.articles.edit', $article));

        $article->refresh();
        $this->assertSame(KnowledgeArticleStatus::Draft, $article->status);
        $this->assertTrue($article->published_at->equalTo($publishedAt));

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.publish', $article))
            ->assertRedirect();

        $this->assertTrue($article->fresh()->published_at->equalTo($publishedAt));
    }

    public function test_slug_collision_is_resolved_server_side(): void
    {
        $staff = $this->editor();
        KnowledgeArticle::factory()->create([
            'created_by' => $staff->id,
            'slug' => 'hari-pangan',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.knowledge.articles.store'), [
                'title' => 'Hari Pangan',
                'slug' => 'hari-pangan',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'hari-pangan']);
        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'hari-pangan-2']);
    }

    public function test_draft_can_be_deleted_and_published_cannot(): void
    {
        $staff = $this->editor();
        $draft = KnowledgeArticle::factory()->create(['created_by' => $staff->id]);
        $published = KnowledgeArticle::factory()->published()->create(['created_by' => $staff->id]);

        $this->actingAs($staff)
            ->delete(route('admin.knowledge.articles.destroy', $draft))
            ->assertRedirect(route('admin.knowledge.articles.index'));

        $this->from(route('admin.knowledge.articles.edit', $published))
            ->actingAs($staff)
            ->delete(route('admin.knowledge.articles.destroy', $published))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('knowledge_articles', ['id' => $draft->id]);
        $this->assertDatabaseHas('knowledge_articles', ['id' => $published->id, 'status' => 'published']);
    }

    private function editor(): User
    {
        return $this->userWithPermissions([
            'knowledge-articles.view',
            'knowledge-articles.create',
            'knowledge-articles.update',
            'knowledge-articles.publish',
            'knowledge-articles.delete',
        ]);
    }
}
