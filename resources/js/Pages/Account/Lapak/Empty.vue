<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import CooperativeWorkspaceCard from "@/Components/Cooperative/CooperativeWorkspaceCard.vue";

defineProps({
    member: { type: Object, required: true },
    cooperative: { type: Object, default: () => ({ state: "none" }) },
});
</script>

<template>
    <FrontendLayout title="Buat Lapak Saya">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
            <div>
                <p class="text-sm text-text-muted">{{ member.legal_name }} · {{ member.membership_number }}</p>
                <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-text-main">Lapak Saya</h1>
            </div>

            <CooperativeWorkspaceCard :cooperative="cooperative" />

            <BaseCard>
                <template #header>Belum ada Lapak</template>
                <p class="text-text-muted leading-relaxed">
                    Lapak Anggota adalah etalase Anda untuk menampilkan produk. Staf AB2TI meninjau data sebelum Lapak tampil untuk publik.
                    Satu anggota hanya boleh punya satu Lapak.
                </p>
                <p class="mt-3 text-text-muted leading-relaxed">
                    Kontak yang Anda isi di Lapak berbeda dari data anggota pribadi. Isi hanya saluran yang boleh dilihat publik setelah disetujui.
                </p>
                <div class="mt-6">
                    <BaseButton :href="route('account.lapak.create')">
                        Buat Lapak Saya
                    </BaseButton>
                </div>
            </BaseCard>

            <Link :href="route('welcome')" class="text-sm text-primary hover:underline">
                Kembali ke beranda
            </Link>
        </div>
    </FrontendLayout>
</template>
