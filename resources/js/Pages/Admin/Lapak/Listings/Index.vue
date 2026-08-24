<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import LapakStatusBadge from "@/Components/Lapak/LapakStatusBadge.vue";
import { usePermission } from "@/Composables/usePermission";

const props = defineProps({
    listings: Object,
    filters: Object,
    statusOptions: Array,
    availabilityOptions: Array,
    lapakOptions: Array,
    categoryOptions: Array,
});

const { can } = usePermission();
const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");
const availability = ref(props.filters.availability || "");
const lapakId = ref(props.filters.lapak_id ? String(props.filters.lapak_id) : "");
const categoryId = ref(props.filters.category_id ? String(props.filters.category_id) : "");

const statusFilterOptions = [{ value: "", label: "Semua status" }, ...props.statusOptions];
const availabilityFilterOptions = [{ value: "", label: "Semua ketersediaan" }, ...props.availabilityOptions];
const lapakFilterOptions = [{ value: "", label: "Semua Lapak" }, ...props.lapakOptions];
const categoryFilterOptions = [{ value: "", label: "Semua kategori" }, ...props.categoryOptions];

let timeout = null;
const applyFilters = () => {
    router.get(
        route("admin.lapak.listings.index"),
        {
            search: search.value,
            status: status.value,
            availability: availability.value,
            lapak_id: lapakId.value,
            category_id: categoryId.value,
        },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 300);
});
watch([status, availability, lapakId, categoryId], applyFilters);
</script>

<template>
    <AppLayout title="Produk Lapak">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="font-semibold text-xl text-text-main leading-tight">Produk Lapak</h2>
                <BaseButton
                    v-if="can('lapak.manage')"
                    :href="route('admin.lapak.listings.create')"
                >
                    Tambah produk
                </BaseButton>
            </div>
        </template>

        <BaseList :items="listings">
            <template #header>
                <p class="text-sm text-text-muted">
                    Produk milik Lapak, bukan akun staf. Status tayang dan ketersediaan adalah dua hal berbeda.
                </p>
            </template>
            <template #actions>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 w-full">
                    <BaseInput id="listing-search" v-model="search" label="Cari" placeholder="Judul produk" />
                    <div>
                        <label class="block font-medium text-sm text-text-main mb-1" for="listing-status">Status</label>
                        <BaseSelect id="listing-status" v-model="status" :options="statusFilterOptions" />
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-text-main mb-1" for="listing-availability">Ketersediaan</label>
                        <BaseSelect id="listing-availability" v-model="availability" :options="availabilityFilterOptions" />
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-text-main mb-1" for="listing-lapak">Lapak</label>
                        <BaseSelect id="listing-lapak" v-model="lapakId" :options="lapakFilterOptions" />
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-text-main mb-1" for="listing-category">Kategori</label>
                        <BaseSelect id="listing-category" v-model="categoryId" :options="categoryFilterOptions" />
                    </div>
                </div>
            </template>
            <template #title="{ item }">{{ item.title }}</template>
            <template #description="{ item }">
                <span>{{ item.lapak_name }}</span>
                <span class="hidden sm:inline text-border">·</span>
                <span>{{ item.member_name }}</span>
                <span class="hidden sm:inline text-border">·</span>
                <span>{{ item.category_name }}</span>
            </template>
            <template #right="{ item }">
                <LapakStatusBadge :status="item.moderation_status" :label="item.moderation_label" />
                <LapakStatusBadge :status="item.availability" :label="item.availability_label" />
                <Link
                    :href="route('admin.lapak.listings.show', item.id)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    Lihat
                </Link>
            </template>
            <template #actions_mobile="{ item }">
                <Link :href="route('admin.lapak.listings.show', item.id)" class="text-sm text-primary">Lihat</Link>
            </template>
            <template #pagination>
                <BasePagination :links="listings.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
