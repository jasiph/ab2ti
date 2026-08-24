<?php

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;

class KnowledgeArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('knowledge-articles.view');
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge-articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge-articles.create');
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge-articles.update');
    }

    public function publish(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge-articles.publish');
    }

    public function delete(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge-articles.delete');
    }
}
