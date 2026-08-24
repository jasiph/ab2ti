<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
</script>

<template>
    <FrontendLayout title="Lapak Saya">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-text-main">Lapak Saya</h1>
            <BaseCard>
                <template #header>Akun belum terhubung</template>
                <p class="text-text-muted leading-relaxed">
                    Halaman ini hanya untuk anggota AB2TI yang akunnya sudah dihubungkan oleh staf.
                    Masuk ke akun tidak membuat Anda menjadi anggota, dan kesamaan email atau nomor telepon tidak cukup untuk menghubungkan.
                </p>
                <p class="mt-4 text-text-muted leading-relaxed">
                    Jika Anda anggota, minta staf AB2TI mengirim undangan tautan akun. Buka undangan itu saat sudah masuk, lalu konfirmasi secara sadar.
                </p>
                <Link
                    :href="route('welcome')"
                    class="inline-flex mt-6 text-sm text-primary hover:underline"
                >
                    Kembali ke beranda
                </Link>
            </BaseCard>
        </div>
    </FrontendLayout>
</template>
