<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import PublicationStatusBadge from "@/Components/Publishing/PublicationStatusBadge.vue";
import PublicationTypeBadge from "@/Components/Publishing/PublicationTypeBadge.vue";
import { usePermission } from "@/Composables/usePermission";

const props = defineProps({
    publications: Object,
    filters: Object,
    typeOptions: Array,
    statusOptions: Array,
});

const { can } = usePermission();
const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");
const type = ref(props.filters.type || "");

const typeFilterOptions = [
    { value: "", label: "Semua jenis" },
    ...props.typeOptions.map((value) => ({
        value,
        label: {
            berita: "Berita",
            kegiatan: "Kegiatan",
            siaran_pers: "Siaran pers",
            liputan_media: "Liputan media",
        }[value] || value,
    })),
];

const statusFilterOptions = [
    { value: "", label: "Semua status" },
    ...props.statusOptions.map((value) => ({
        value,
        label: value === "published" ? "Terbit" : "Draf",
    })),
];

let timeout = null;
const applyFilters = () => {
    router.get(
        route("admin.publishing.publications.index"),
        { search: search.value, status: status.value, type: type.value },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 300);
});
watch([status, type], applyFilters);

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleString("id-ID");
};
</script>

<template>
    <AppLayout :title="$t('Publications')">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t("Publications") }}
                </h2>
                <BaseButton
                    v-if="can('publishing-publications.create')"
                    :href="route('admin.publishing.publications.create')"
                >
                    {{ $t("New publication") }}
                </BaseButton>
            </div>
        </template>

        <BaseList :items="publications">
            <template #actions>
                <div class="flex flex-col sm:flex-row gap-3 w-full">
                    <div class="w-full sm:w-64">
                        <BaseInput
                            id="publication-search"
                            v-model="search"
                            :label="$t('Search')"
                            :placeholder="$t('Search by title')"
                        />
                    </div>
                    <div class="w-full sm:w-44">
                        <label for="publication-status" class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Status") }}
                        </label>
                        <BaseSelect id="publication-status" v-model="status" :options="statusFilterOptions" />
                    </div>
                    <div class="w-full sm:w-48">
                        <label for="publication-type" class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Type") }}
                        </label>
                        <BaseSelect id="publication-type" v-model="type" :options="typeFilterOptions" />
                    </div>
                </div>
            </template>

            <template #title="{ item }">
                {{ item.title }}
            </template>

            <template #description="{ item }">
                <PublicationTypeBadge :type="item.type" />
            </template>

            <template #meta_mobile="{ item }">
                <PublicationStatusBadge :status="item.status" />
            </template>

            <template #right="{ item }">
                <PublicationStatusBadge :status="item.status" />
                <span class="text-xs text-text-muted">{{ formatDate(item.updated_at) }}</span>
                <Link
                    :href="route('admin.publishing.publications.edit', item.id)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{ $t("Open") }}
                </Link>
            </template>

            <template #actions_mobile="{ item }">
                <span class="text-xs text-text-muted mr-auto">{{ formatDate(item.updated_at) }}</span>
                <BaseButton
                    :href="route('admin.publishing.publications.edit', item.id)"
                    variant="secondary"
                    size="sm"
                >
                    {{ $t("Open") }}
                </BaseButton>
            </template>

            <template #pagination>
                <BasePagination :links="publications.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
