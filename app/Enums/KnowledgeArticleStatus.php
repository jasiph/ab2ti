<?php

namespace App\Enums;

enum KnowledgeArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Published,
            self::Published => $next === self::Draft,
        };
    }
}
