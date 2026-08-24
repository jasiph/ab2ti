<script setup>
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import LapakStatusBadge from "@/Components/Lapak/LapakStatusBadge.vue";
import LapakModerationActions from "@/Components/Lapak/LapakModerationActions.vue";
import { usePermission } from "@/Composables/usePermission";

const props = defineProps({
    listing: Object,
});

const { can } = usePermission();

const setAvailability = (availability) => {
    router.post(route("admin.lapak.listings.availability", props.listing.id), {
        availability,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :title="listing.title">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <Link :href="route('admin.lapak.listings.index')" class="text-sm text-text-muted hover:text-text-main">
                        ← Daftar produk
                    </Link>
                    <h2 class="font-semibold text-xl text-text-main leading-tight mt-1">{{ listing.title }}</h2>
                </div>
                <BaseButton
                    v-if="can('lapak.manage')"
                    :href="route('admin.lapak.listings.edit', listing.id)"
                    variant="secondary"
                >
                    Ubah data
                </BaseButton>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
            <BaseCard>
                <template #header>Identitas produk</template>
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <LapakStatusBadge :status="listing.moderation_status" :label="listing.moderation_label" />
                    <LapakStatusBadge :status="listing.availability" :label="listing.availability_label" />
                </div>
                <p class="text-sm text-text-muted mb-4">
                    {{ listing.ineligible_reason || "Memenuhi syarat tayang publik." }}
                </p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">Berada di Lapak</dt>
                        <dd class="font-medium text-text-main">
                            <Link :href="route('admin.lapak.storefronts.show', listing.lapak_id)" class="text-primary hover:underline">
                                {{ listing.lapak_name }}
                            </Link>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Pemilik Lapak</dt>
                        <dd>{{ listing.member_name }}</dd>
                        <dd class="font-mono text-xs text-text-muted">{{ listing.membership_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Harga</dt>
                        <dd>{{ listing.price_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Slug</dt>
                        <dd class="font-mono text-xs">{{ listing.slug }}</dd>
                    </div>
                </dl>
                <p v-if="listing.moderation_note" class="text-sm text-danger mt-4">Alasan: {{ listing.moderation_note }}</p>
            </BaseCard>

            <BaseCard>
                <template #header>Gambar produk</template>
                <div v-if="listing.images?.length" class="flex flex-wrap gap-3">
                    <img
                        v-for="image in listing.images"
                        :key="image.id"
                        :src="image.thumbnail_url || image.url"
                        alt=""
                        class="h-24 w-24 rounded border border-border object-cover"
                    />
                </div>
                <p v-else class="text-sm text-text-muted">Belum ada gambar.</p>
            </BaseCard>

            <BaseCard v-if="can('lapak.manage')">
                <template #header>Ketersediaan</template>
                <p class="text-sm text-text-muted mb-3">
                    Ketersediaan terpisah dari status publikasi. Produk yang sudah tayang tetap bisa ditandai tidak tersedia.
                </p>
                <div class="flex flex-wrap gap-2">
                    <BaseButton
                        type="button"
                        size="sm"
                        :variant="listing.availability === 'available' ? 'primary' : 'secondary'"
                        @click="setAvailability('available')"
                    >
                        Tersedia
                    </BaseButton>
                    <BaseButton
                        type="button"
                        size="sm"
                        :variant="listing.availability === 'unavailable' ? 'primary' : 'secondary'"
                        @click="setAvailability('unavailable')"
                    >
                        Tidak tersedia
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>Moderasi</template>
                <LapakModerationActions
                    :transitions="listing.allowed_transitions"
                    :can-moderate="listing.can_moderate"
                    :action="route('admin.lapak.listings.moderate', listing.id)"
                />
            </BaseCard>
        </div>
    </AppLayout>
</template>
