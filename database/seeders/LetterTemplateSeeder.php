<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LetterTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Surat Baptis Anak',
                'code' => 'BAPTIS-ANAK',
                'data_schema' => [
                    'nama_anak' => 'text',
                    'nama_ayah' => 'text',
                    'nama_ibu' => 'text',
                    'tempat_lahir' => 'text',
                    'tanggal_lahir' => 'date',
                    'tanggal_baptis' => 'date',
                    'pendeta' => 'text',
                ],
                'content_template' => '
<div style="text-align: center;">
    <h3>SURAT BAPTISAN KUDUS</h3>
    <p>Nomor: {{ letter_number }}</p>
</div>
<br>
<p>Majelis Jemaat Gereja Presbiterian dengan ini menerangkan bahwa anak:</p>
<div style="text-align: center;">
    <h2>{{ nama_anak }}</h2>
</div>
<p>Lahir di <strong>{{ tempat_lahir }}</strong> pada tanggal <strong>{{ tanggal_lahir }}</strong>.</p>
<p>Anak dari Bapak <strong>{{ nama_ayah }}</strong> dan Ibu <strong>{{ nama_ibu }}</strong>.</p>
<br>
<p>Telah menerima sakramen <strong>Baptisan Kudus</strong> sesuai dengan pengakuan iman Gereja Presbiterian/Reformed, pada:</p>
<table>
    <tr>
        <td style="width: 150px;">Tanggal</td>
        <td>: {{ tanggal_baptis }}</td>
    </tr>
    <tr>
        <td>Dilayani Oleh</td>
        <td>: Pdt. {{ pendeta }}</td>
    </tr>
</table>
<br>
<p>Biarlah anak ini bertumbuh dalam iman dan pengenalan akan Tuhan Yesus Kristus.</p>
                ',
            ],
            [
                'name' => 'Surat Sidi (Pengakuan Percaya)',
                'code' => 'SIDI',
                'data_schema' => [
                    'nama_lengkap' => 'text',
                    'nomor_induk' => 'text',
                    'tempat_lahir' => 'text',
                    'tanggal_lahir' => 'date',
                    'tanggal_sidi' => 'date',
                    'pendeta' => 'text',
                    'ayat_nats' => 'text',
                ],
                'content_template' => '
<div style="text-align: center;">
    <h3>SURAT PENGAKUAN PERCAYA (SIDI)</h3>
    <p>Nomor: {{ letter_number }}</p>
</div>
<br>
<p>Majelis Jemaat menerangkan bahwa saudara/i:</p>
<div style="text-align: center;">
    <h2>{{ nama_lengkap }}</h2>
    <p>NIA: {{ nomor_induk }}</p>
</div>
<p>Lahir di {{ tempat_lahir }}, pada tanggal {{ tanggal_lahir }}.</p>
<br>
<p>Telah mengaku percaya (Sidi) di hadapan Allah dan Jemaat-Nya pada tanggal <strong>{{ tanggal_sidi }}</strong>, yang dilayani oleh <strong>Pdt. {{ pendeta }}</strong>.</p>
<br>
<p>Dengan demikian saudara/i tersebut telah diterima sebagai anggota sidi jemaat yang berhak penuh mengambil bagian dalam Perjamuan Kudus dan bertanggung jawab dalam pelayanan gereja.</p>
<br>
<p><em>"{{ ayat_nats }}"</em></p>
                ',
            ],
            [
                'name' => 'Surat Keterangan Anggota',
                'code' => 'SK-ANGGOTA',
                'data_schema' => [
                    'nama_jemaat' => 'text',
                    'alamat' => 'text',
                    'bergabung_sejak' => 'text', // e.g. Tahun 2010
                    'keperluan' => 'text',
                ],
                'content_template' => '
<div style="text-align: center;">
    <h3>SURAT KETERANGAN KEANGGOTAAN</h3>
    <p>Nomor: {{ letter_number }}</p>
</div>
<br>
<p>Yang bertanda tangan di bawah ini, Majelis Jemaat menerangkan bahwa:</p>
<table>
    <tr>
        <td style="width: 150px;">Nama</td>
        <td>: <strong>{{ nama_jemaat }}</strong></td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td>: {{ alamat }}</td>
    </tr>
</table>
<br>
<p>Adalah benar anggota jemaat kami yang aktif dan terdaftar sejak <strong>{{ bergabung_sejak }}</strong> hingga saat ini.</p>
<p>Surat keterangan ini diberikan untuk keperluan: <strong>{{ keperluan }}</strong>.</p>
<br>
<p>Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>
                ',
            ]
        ];

        foreach ($templates as $template) {
            LetterTemplate::updateOrCreate(
            ['code' => $template['code']],
                $template
            );
        }
    }
}
