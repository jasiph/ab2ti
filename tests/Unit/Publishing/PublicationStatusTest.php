<?php

namespace Tests\Unit\Publishing;

use App\Enums\PublicationStatus;
use Tests\TestCase;

class PublicationStatusTest extends TestCase
{
    public function test_phase_2b_allows_only_draft_and_published_transitions(): void
    {
        $draft = PublicationStatus::Draft;
        $published = PublicationStatus::Published;

        $this->assertTrue($draft->canTransitionTo($published));
        $this->assertFalse($draft->canTransitionTo($draft));
        $this->assertTrue($published->canTransitionTo($draft));
        $this->assertFalse($published->canTransitionTo($published));
    }
}
