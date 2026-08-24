<?php

namespace App\Actions\Lapak;

use App\Enums\LapakModerationStatus;
use App\Exceptions\UniqueMemberLapakException;
use App\Models\Lapak;
use App\Models\Member;
use App\Models\User;
use App\Support\Lapak\LapakMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateLapak
{
    public function __construct(private readonly LapakMedia $media) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        array $payload,
        User $actor,
        ?UploadedFile $coverUpload = null,
        ?int $existingCoverId = null,
    ): Lapak {
        return DB::transaction(function () use ($payload, $actor, $coverUpload, $existingCoverId) {
            $member = Member::query()->lockForUpdate()->findOrFail($payload['member_id']);

            if (Lapak::query()->where('member_id', $member->id)->exists()) {
                throw new UniqueMemberLapakException;
            }

            $lapak = new Lapak;
            $lapak->fill($payload);
            $lapak->member_id = $member->id;
            $lapak->moderation_status = LapakModerationStatus::Draft;
            $lapak->created_by = $actor->id;
            $lapak->updated_by = $actor->id;
            $lapak->save();

            $this->media->syncCover($lapak, $coverUpload, $existingCoverId, false, $actor->id);

            return $lapak;
        });
    }
}
