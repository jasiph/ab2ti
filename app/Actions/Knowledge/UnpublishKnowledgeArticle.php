<?php

namespace App\Actions\Knowledge;

use App\Enums\KnowledgeArticleStatus;
use App\Exceptions\InvalidKnowledgeArticleTransition;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishKnowledgeArticle
{
    public function handle(KnowledgeArticle $article, User $actor): KnowledgeArticle
    {
        return DB::transaction(function () use ($article, $actor) {
            /** @var KnowledgeArticle $article */
            $article = KnowledgeArticle::query()->lockForUpdate()->findOrFail($article->id);

            if ($article->status === KnowledgeArticleStatus::Draft) {
                return $article;
            }

            if (! $article->status->canTransitionTo(KnowledgeArticleStatus::Draft)) {
                throw new InvalidKnowledgeArticleTransition(
                    'Artikel pengetahuan ini tidak dapat dibatalkan terbitannya pada status saat ini.',
                );
            }

            $article->status = KnowledgeArticleStatus::Draft;
            $article->updated_by = $actor->id;
            $article->save();

            return $article;
        });
    }
}
