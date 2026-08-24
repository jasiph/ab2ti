<?php

namespace App\Support\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Exceptions\InvalidMembershipApplicationTransition;
use App\Models\MembershipApplication;
use App\Models\User;

final class MembershipApplicationLifecycle
{
    /**
     * Persist the first status for a new application (public submit).
     *
     * @param  callable(MembershipApplication): void|null  $apply
     */
    public function initialize(
        MembershipApplication $application,
        MembershipApplicationStatus $to,
        string $actorContext,
        ?string $reason = null,
        ?callable $apply = null,
    ): void {
        if ($application->exists && $application->getOriginal('status') !== null) {
            throw new InvalidMembershipApplicationTransition(
                'Permohonan yang sudah tersimpan tidak dapat diinisialisasi ulang.',
            );
        }

        if ($to !== MembershipApplicationStatus::Submitted) {
            throw new InvalidMembershipApplicationTransition(
                'Permohonan baru hanya dapat dimulai pada status submitted.',
            );
        }

        $application->status = $to;

        if ($apply !== null) {
            $apply($application);
        }

        $application->save();
        $application->recordTransition(
            from: null,
            to: $to,
            actor: null,
            actorContext: $actorContext,
            reason: $reason,
        );
    }

    /**
     * Apply an allowed status change. Callers must already hold a row lock
     * when the transition is part of a concurrent staff command.
     *
     * Idempotent same-status handling belongs in the Action, not here.
     *
     * @param  callable(MembershipApplication): void  $apply
     */
    public function transition(
        MembershipApplication $application,
        MembershipApplicationStatus $to,
        ?User $actor,
        string $actorContext,
        ?string $reason,
        callable $apply,
    ): void {
        $from = $application->status;

        if (! $from instanceof MembershipApplicationStatus) {
            throw new InvalidMembershipApplicationTransition(
                'Status permohonan tidak valid.',
            );
        }

        if (! $from->canTransitionTo($to)) {
            throw new InvalidMembershipApplicationTransition($this->message($from, $to));
        }

        $application->status = $to;
        $apply($application);
        $application->save();
        $application->recordTransition(
            from: $from,
            to: $to,
            actor: $actor,
            actorContext: $actorContext,
            reason: $reason,
        );
    }

    private function message(MembershipApplicationStatus $from, MembershipApplicationStatus $to): string
    {
        return match ($to) {
            MembershipApplicationStatus::UnderReview => 'Permohonan ini tidak dapat mulai ditinjau pada status saat ini.',
            MembershipApplicationStatus::Approved => 'Permohonan hanya dapat disetujui saat sedang ditinjau.',
            MembershipApplicationStatus::Rejected => 'Permohonan hanya dapat ditolak saat sedang ditinjau.',
            MembershipApplicationStatus::RevisionRequested => 'Permohonan hanya dapat diminta revisi saat sedang ditinjau.',
            MembershipApplicationStatus::Submitted => 'Permohonan hanya dapat dikirim ulang saat revisi diminta.',
            default => sprintf(
                'Transisi status permohonan dari %s ke %s tidak diizinkan.',
                $from->value,
                $to->value,
            ),
        };
    }
}
