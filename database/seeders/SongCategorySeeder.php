<?php

namespace Database\Seeders;

use App\Models\SongCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SongCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // 1. Berdasarkan Alur Liturgi (Fungsional)
            'Litutgy' => [
                'Call to Worship' => 'Panggilan Beribadah',
                'Praise & Thanksgiving' => 'Pujian & Ucapan Syukur',
                'Confession' => 'Pengakuan Dosa & Penyesalan',
                'Preparation' => 'Persiapan Firman',
                'Response' => 'Respons Firman (Komitmen)',
                'Offering' => 'Persembahan',
                'Benediction' => 'Pengutusan & Berkat',
            ],
            // 2. Berdasarkan Tema Teologis (Substansi)
            'Theology' => [
                'Attributes of God' => 'Atribut Allah',
                'Christological' => 'Kristologis',
                'Pneumatological' => 'Pneumatologis',
                'Ecclesiological' => 'Identitas Jemaat',
                'Eschatological' => 'Eskatologis',
            ],
            // 3. Berdasarkan Nuansa Musikal (Atmosfer)
            'Musical' => [
                'Majestic' => 'Majestic/Anthem',
                'Intimate' => 'Intimate/Reflective',
                'Joyful' => 'Joyful/Celebration',
                'Meditative' => 'Meditative',
            ],
            // 4. Berdasarkan Genre dan Era (Gaya)
            'Genre' => [
                'Hymns' => 'Hymns (Tradisional)',
                'Contemporary' => 'Contemporary Worship (CCM)',
                'Gospel' => 'Gospel',
                'Kids/Youth' => 'Lagu Sekolah Minggu/Remaja',
            ],
            // 5. Berdasarkan Kalender Gerejawi (Khusus)
            'Calendar' => [
                'Advent/Christmas' => 'Adven & Natal',
                'Lent/Easter' => 'Pra-Paskah & Paskah',
                'Ascension/Pentecost' => 'Kenaikan & Pentakosta',
            ],
        ];

        foreach ($categories as $type => $items) {
            foreach ($items as $name => $description) {
                // We use the Indonesian description as the Name for easier searching by users
                // Or we can use the English key as name and desc as something else. 
                // Let's use the Indonesian value as the Name stored in DB.
                SongCategory::firstOrCreate(
                ['name' => $description],
                [
                    'slug' => Str::slug($description),
                    'type' => $type,
                ]
                );
            }
        }
    }
}
