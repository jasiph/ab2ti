<?php

namespace Database\Seeders;

use App\Models\BlockTemplate;
use Illuminate\Database\Seeder;

class BlockTemplateSeeder extends Seeder
{
    public function run(): void
    {
        BlockTemplate::create([
            'name' => 'Highlight Card (Android Style)',
            'description' => 'Kartu dengan warna latar belakang yang mencolok, border-radius khas Android, dan shadow.',
            'is_active' => true,
            'html_content' => <<<HTML

<div class="bg-surface-variant dark:bg-surface-variant/30 rounded-2xl p-6 shadow-md border border-border my-6">
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <h3 class="text-xl font-bold text-text-main mt-0 mb-2">Informasi Penting</h3>
            <p class="text-text-main/80 m-0">Ganti teks ini dengan informasi penting yang ingin Anda sampaikan. Template ini mengikuti prinsip desain Android dengan border radius yang lebih besar dan penggunaan warna semantik.</p>
        </div>
    </div>
</div>
HTML,
        ]);

        BlockTemplate::create([
            'name' => 'Beautiful Image with Caption',
            'description' => 'Gambar dengan keterangan teks melayang di bawahnya.',
            'is_active' => true,
            'html_content' => <<<HTML

<figure class="relative overflow-hidden rounded-2xl shadow-lg my-8 group">
    <img src="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80" alt="Ilustrasi Lanskap" class="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105" />
    <figcaption class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/80 to-transparent p-6 pt-12 text-white">
        <h4 class="text-lg font-bold m-0 text-white">Lanskap Biodiversitas</h4>
        <p class="text-sm text-white/80 m-0 mt-1">Hutan tropis Indonesia menyimpan jutaan spesies flora dan fauna.</p>
    </figcaption>
</figure>
HTML,
        ]);

        BlockTemplate::create([
            'name' => 'Two Columns Feature',
            'description' => 'Dua kolom berdampingan untuk menjelaskan fitur atau layanan.',
            'is_active' => true,
            'html_content' => <<<HTML

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 my-8">
    <div class="bg-background rounded-xl p-5 border border-border shadow-sm">
        <h4 class="text-primary font-bold mt-0">Visi Kami</h4>
        <p class="text-text-main/80 text-sm mb-0">Menjadi pusat konservasi biodiversitas terkemuka dengan fokus pada pemberdayaan masyarakat lokal.</p>
    </div>
    <div class="bg-background rounded-xl p-5 border border-border shadow-sm">
        <h4 class="text-primary font-bold mt-0">Misi Kami</h4>
        <p class="text-text-main/80 text-sm mb-0">Melakukan penelitian interdisipliner, edukasi publik, dan preservasi ekosistem berkelanjutan.</p>
    </div>
</div>
HTML,
        ]);
    }
}
