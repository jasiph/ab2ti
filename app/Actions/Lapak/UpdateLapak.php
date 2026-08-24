<?php

namespace App\Actions\Lapak;

use App\Exceptions\InvalidLapakTransition;
use App\Models\Lapak;
use App\Models\User;
use App\Support\Lapak\LapakMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateLapak
{
    public function __construct(private readonly LapakMedia $media) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Lapak $lapak,
        array $payload,
        User $actor,
        ?UploadedFile $coverUpload = null,
        ?int $existingCoverId = null,
        bool $removeCover = false,
    ): Lapak {
        return DB::transaction(function () use ($lapak, $payload, $actor, $coverUpload, $existingCoverId, $removeCover) {
            $lapak = Lapak::query()->lockForUpdate()->findOrFail($lapak->id);

            if ($actor->ownsLapak($lapak) && ! $lapak->moderation_status->ownerMayEditContent()) {
                throw new InvalidLapakTransition(
                    'Lapak tidak dapat diubah pada status '.$lapak->moderation_status->ownerLabel().'.',
                );
            }

            $lapak->fill($payload);
            $lapak->updated_by = $actor->id;
            $lapak->save();

            $this->media->syncCover($lapak, $coverUpload, $existingCoverId, $removeCover, $actor->id);

            return $lapak;
        });
    }
}
