<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import LapakStatusBadge from "@/Components/Lapak/LapakStatusBadge.vue";
import LapakModerationActions from "@/Components/Lapak/LapakModerationActions.vue";
import { usePermission } from "@/Composables/usePermission";

defineProps({
    lapak: Object,
    listings: Array,
});

const { can } = usePermission();
</script>

<template>
    <AppLayout :title="lapak.name">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <Link :href="route('admin.lapak.storefronts.index')" class="text-sm text-text-muted hover:text-text-main">
                        ← Daftar Lapak
                    </Link>
                    <h2 class="font-semibold text-xl text-text-main leading-tight mt-1">{{ lapak.name }}</h2>
                </div>
                <BaseButton
                    v-if="can('lapak.manage')"
                    :href="route('admin.lapak.storefronts.edit', lapak.id)"
                    variant="secondary"
                >
                    Ubah data
                </BaseButton>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
            <BaseCard class="p-5 space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <LapakStatusBadge :status="lapak.moderation_status" :label="lapak.moderation_label" />
                    <span class="text-sm text-text-muted">
                        {{ lapak.public_eligible ? "Memenuhi syarat tayang publik." : "Belum memenuhi syarat tayang publik." }}
                    </span>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">Pemilik</dt>
                        <dd class="font-medium text-text-main">{{ lapak.member_name }}</dd>
                        <dd class="font-mono text-xs text-text-muted">{{ lapak.membership_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Lokasi</dt>
                        <dd>{{ lapak.location_text || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Kontak</dt>
                        <dd>{{ lapak.contact_phone || lapak.contact_whatsapp || lapak.contact_email || "Belum diisi" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Slug</dt>
                        <dd class="font-mono text-xs">{{ lapak.slug }}</dd>
                    </div>
                </dl>
                <p v-if="lapak.moderation_note" class="text-sm text-danger">Alasan: {{ lapak.moderation_note }}</p>
                <LapakModerationActions
                    :transitions="lapak.allowed_transitions"
                    :can-moderate="lapak.can_moderate"
                    :action="route('admin.lapak.storefronts.moderate', lapak.id)"
                />
            </BaseCard>

            <BaseCard class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-text-main">Produk di Lapak ini</h3>
                    <BaseButton
                        v-if="can('lapak.manage')"
                        size="sm"
                        :href="route('admin.lapak.listings.create', { lapak_id: lapak.id })"
                    >
                        Tambah produk
                    </BaseButton>
                </div>
                <p v-if="!listings.length" class="text-sm text-text-muted">Belum ada produk.</p>
                <ul v-else class="divide-y divide-border">
                    <li v-for="item in listings" :key="item.id" class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-text-main">{{ item.title }}</p>
                            <p class="text-xs text-text-muted">{{ item.category_name }} · {{ item.price_label }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <LapakStatusBadge :status="item.moderation_status" :label="item.moderation_label" />
                            <Link :href="route('admin.lapak.listings.show', item.id)" class="text-sm text-primary">Lihat</Link>
                        </div>
                    </li>
                </ul>
            </BaseCard>
        </div>
    </AppLayout>
</template>
