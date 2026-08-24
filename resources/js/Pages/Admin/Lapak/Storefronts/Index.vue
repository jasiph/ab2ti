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
    lapaks: Object,
    filters: Object,
    statusOptions: Array,
});

const { can } = usePermission();
const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");

const statusFilterOptions = [
    { value: "", label: "Semua status" },
    ...props.statusOptions,
];

let timeout = null;
const applyFilters = () => {
    router.get(
        route("admin.lapak.storefronts.index"),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 300);
});
watch(status, applyFilters);
</script>

<template>
    <AppLayout title="Lapak Anggota">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="font-semibold text-xl text-text-main leading-tight">Lapak Anggota</h2>
                <BaseButton
                    v-if="can('lapak.manage')"
                    :href="route('admin.lapak.storefronts.create')"
                >
                    Buat Lapak
                </BaseButton>
            </div>
        </template>

        <BaseList :items="lapaks">
            <template #header>
                <p class="text-sm text-text-muted">
                    Satu anggota AB2TI memiliki paling banyak satu Lapak. Produk dikelola di dalam Lapak.
                </p>
            </template>
            <template #actions>
                <div class="flex flex-col sm:flex-row gap-3 w-full">
                    <div class="w-full sm:w-72">
                        <BaseInput
                            id="lapak-search"
                            v-model="search"
                            label="Cari"
                            placeholder="Nama Lapak, anggota, atau nomor anggota"
                        />
                    </div>
                    <div class="w-full sm:w-52">
                        <label class="block font-medium text-sm text-text-main mb-1" for="lapak-status">Status</label>
                        <BaseSelect id="lapak-status" v-model="status" :options="statusFilterOptions" />
                    </div>
                </div>
            </template>
            <template #title="{ item }">{{ item.name }}</template>
            <template #description="{ item }">
                <span>Milik {{ item.member_name }}</span>
                <span class="hidden sm:inline text-border">·</span>
                <span class="font-mono text-xs">{{ item.membership_number }}</span>
            </template>
            <template #right="{ item }">
                <LapakStatusBadge :status="item.moderation_status" :label="item.moderation_label" />
                <Link
                    :href="route('admin.lapak.storefronts.show', item.id)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    Lihat
                </Link>
            </template>
            <template #actions_mobile="{ item }">
                <Link :href="route('admin.lapak.storefronts.show', item.id)" class="text-sm text-primary">Lihat</Link>
            </template>
            <template #pagination>
                <BasePagination :links="lapaks.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
