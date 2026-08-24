<script setup>
import { Link, router } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";

const props = defineProps({
    lapak: { type: Object, required: true },
    listings: { type: Array, default: () => [] },
});

const submitListing = (id) => {
    router.post(route("account.lapak.listings.submit", id));
};

const setAvailability = (item, availability) => {
    router.post(route("account.lapak.listings.availability", item.id), { availability });
};
</script>

<template>
    <FrontendLayout title="Produk Lapak Saya">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <Link :href="route('account.lapak.index')" class="text-sm text-primary hover:underline">← Lapak Saya</Link>
                    <h1 class="mt-2 text-2xl font-bold text-text-main">Produk</h1>
                    <p class="text-sm text-text-muted mt-1">{{ lapak.name }}</p>
                </div>
                <BaseButton :href="route('account.lapak.listings.create')" size="sm">
                    Tambah produk
                </BaseButton>
            </div>

            <p v-if="!listings.length" class="text-text-muted">Belum ada produk. Tambahkan produk, lalu kirim untuk ditinjau.</p>

            <ul class="space-y-4">
                <li v-for="item in listings" :key="item.id">
                    <BaseCard>
                        <div class="flex gap-4">
                            <img
                                v-if="item.images?.[0]"
                                :src="item.images[0].thumbnail_url || item.images[0].url"
                                alt=""
                                class="h-20 w-20 rounded object-cover border border-border shrink-0"
                            />
                            <div class="h-20 w-20 rounded bg-surface-variant border border-border shrink-0" v-else />
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-text-main">{{ item.title }}</p>
                                        <p class="text-sm text-text-muted">
                                            {{ item.category_name || "Tanpa kategori" }} · {{ item.price_label }}
                                        </p>
                                    </div>
                                    <span class="text-xs rounded-full px-2 py-1 bg-surface-variant text-text-muted">
                                        {{ item.moderation_label }}
                                    </span>
                                </div>
                                <p class="text-sm text-text-muted">{{ item.guidance.why || item.guidance.next }}</p>
                                <p v-if="item.moderation_note && item.moderation_status === 'rejected'" class="text-sm text-danger">
                                    Catatan staf: {{ item.moderation_note }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <BaseButton
                                        v-if="item.can_edit"
                                        :href="route('account.lapak.listings.edit', item.id)"
                                        size="sm"
                                        variant="secondary"
                                    >
                                        Ubah
                                    </BaseButton>
                                    <BaseButton
                                        v-if="item.can_submit"
                                        type="button"
                                        size="sm"
                                        @click="submitListing(item.id)"
                                    >
                                        Kirim untuk Ditinjau
                                    </BaseButton>
                                    <BaseButton
                                        v-if="item.can_change_availability && item.availability === 'available'"
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="setAvailability(item, 'unavailable')"
                                    >
                                        Tandai tidak tersedia
                                    </BaseButton>
                                    <BaseButton
                                        v-else-if="item.can_change_availability"
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="setAvailability(item, 'available')"
                                    >
                                        Tandai tersedia
                                    </BaseButton>
                                </div>
                            </div>
                        </div>
                    </BaseCard>
                </li>
            </ul>
        </div>
    </FrontendLayout>
</template>
