<script setup>
import { Link, router } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";

const props = defineProps({
    member: { type: Object, required: true },
    lapak: { type: Object, required: true },
    listing_counts: { type: Object, required: true },
    listings: { type: Array, default: () => [] },
});

const submitLapak = () => {
    router.post(route("account.lapak.submit", props.lapak.id));
};
</script>

<template>
    <FrontendLayout title="Lapak Saya">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <p class="text-sm text-text-muted">{{ member.legal_name }} · {{ member.membership_number }}</p>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-text-main">{{ lapak.name }}</h1>
                    <p class="mt-2 text-sm">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="lapak.public_visible ? 'bg-primary/10 text-primary' : 'bg-surface-variant text-text-muted'"
                        >
                            {{ lapak.moderation_label }}
                        </span>
                        <span class="ml-2 text-text-muted">
                            {{ lapak.public_visible ? "Terlihat publik (setelah etalase publik dibuka)." : "Belum terlihat publik." }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <BaseButton
                        v-if="lapak.can_edit"
                        :href="route('account.lapak.edit', lapak.id)"
                        variant="secondary"
                        size="sm"
                    >
                        Ubah etalase
                    </BaseButton>
                    <BaseButton
                        v-if="lapak.can_submit"
                        type="button"
                        size="sm"
                        @click="submitLapak"
                    >
                        Kirim untuk Ditinjau
                    </BaseButton>
                </div>
            </div>

            <BaseCard>
                <template #header>Status Lapak</template>
                <p v-if="lapak.guidance.why" class="text-sm text-text-main">{{ lapak.guidance.why }}</p>
                <p class="text-sm text-text-muted mt-2">{{ lapak.guidance.next }}</p>
                <p v-if="lapak.moderation_note && lapak.moderation_status === 'rejected'" class="mt-3 text-sm rounded-md bg-danger/5 border border-danger/30 p-3">
                    Catatan staf: {{ lapak.moderation_note }}
                </p>
            </BaseCard>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-lg border border-border p-4">
                    <p class="text-xs text-text-muted">Produk</p>
                    <p class="mt-1 text-xl font-semibold">{{ listing_counts.total }}</p>
                </div>
                <div class="rounded-lg border border-border p-4">
                    <p class="text-xs text-text-muted">Tersedia</p>
                    <p class="mt-1 text-xl font-semibold">{{ listing_counts.available }}</p>
                </div>
                <div class="rounded-lg border border-border p-4">
                    <p class="text-xs text-text-muted">Menunggu Tinjauan</p>
                    <p class="mt-1 text-xl font-semibold">{{ listing_counts.pending }}</p>
                </div>
                <div class="rounded-lg border border-border p-4">
                    <p class="text-xs text-text-muted">Perlu Diperbaiki</p>
                    <p class="mt-1 text-xl font-semibold">{{ listing_counts.rejected }}</p>
                </div>
            </div>

            <BaseCard>
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <span>Produk</span>
                        <Link :href="route('account.lapak.listings.index')" class="text-sm font-normal text-primary hover:underline">
                            Kelola produk
                        </Link>
                    </div>
                </template>
                <p v-if="!listings.length" class="text-sm text-text-muted">Belum ada produk.</p>
                <ul v-else class="divide-y divide-border">
                    <li v-for="item in listings.slice(0, 5)" :key="item.id" class="py-3 flex items-center gap-3">
                        <img
                            v-if="item.images?.[0]"
                            :src="item.images[0].thumbnail_url || item.images[0].url"
                            alt=""
                            class="h-12 w-12 rounded object-cover border border-border"
                        />
                        <div class="h-12 w-12 rounded bg-surface-variant border border-border" v-else />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium truncate">{{ item.title }}</p>
                            <p class="text-xs text-text-muted">{{ item.moderation_label }} · {{ item.availability_label }}</p>
                        </div>
                    </li>
                </ul>
                <div class="mt-4">
                    <BaseButton :href="route('account.lapak.listings.create')" size="sm" variant="secondary">
                        Tambah produk
                    </BaseButton>
                </div>
            </BaseCard>
        </div>
    </FrontendLayout>
</template>
