<?php

namespace App\Actions\Knowledge;

use App\Enums\KnowledgeArticleStatus;
use App\Exceptions\InvalidKnowledgeArticleTransition;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishKnowledgeArticle
{
    public function handle(KnowledgeArticle $article, User $actor): KnowledgeArticle
    {
        return DB::transaction(function () use ($article, $actor) {
            /** @var KnowledgeArticle $article */
            $article = KnowledgeArticle::query()->lockForUpdate()->findOrFail($article->id);

            if ($article->status === KnowledgeArticleStatus::Published) {
                return $article;
            }

            if (! $article->status->canTransitionTo(KnowledgeArticleStatus::Published)) {
                throw new InvalidKnowledgeArticleTransition(
                    'Artikel pengetahuan ini tidak dapat diterbitkan pada status saat ini.',
                );
            }

            if (blank($article->title) || blank($article->slug)) {
                throw new InvalidKnowledgeArticleTransition(
                    'Artikel pengetahuan harus memiliki judul dan slug sebelum diterbitkan.',
                );
            }

            $article->status = KnowledgeArticleStatus::Published;
            $article->published_at = $article->published_at ?? now();
            $article->updated_by = $actor->id;
            $article->save();

            return $article;
        });
    }
}
