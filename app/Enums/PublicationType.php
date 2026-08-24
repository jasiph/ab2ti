<?php

namespace App\Enums;

enum PublicationType: string
{
    case Berita = 'berita';
    case Kegiatan = 'kegiatan';
    case SiaranPers = 'siaran_pers';
    case LiputanMedia = 'liputan_media';

    public function publicPath(): string
    {
        return match ($this) {
            self::Berita => 'berita',
            self::Kegiatan => 'kegiatan',
            self::SiaranPers => 'siaran-pers',
            self::LiputanMedia => 'liputan-media',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Berita => 'Berita',
            self::Kegiatan => 'Kegiatan',
            self::SiaranPers => 'Siaran pers',
            self::LiputanMedia => 'Liputan media',
        };
    }

    public static function fromPublicPath(string $path): ?self
    {
        return match ($path) {
            'berita' => self::Berita,
            'kegiatan' => self::Kegiatan,
            'siaran-pers' => self::SiaranPers,
            'liputan-media' => self::LiputanMedia,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function publicPathConstraint(): array
    {
        return array_map(
            fn (self $type): string => $type->publicPath(),
            self::cases(),
        );
    }
}
