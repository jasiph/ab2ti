<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import KnowledgeStatusBadge from "@/Components/Knowledge/KnowledgeStatusBadge.vue";
import { usePermission } from "@/Composables/usePermission";

const props = defineProps({
    articles: Object,
    filters: Object,
    statusOptions: Array,
});

const { can } = usePermission();
const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");

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
        route("admin.knowledge.articles.index"),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 300);
});
watch(status, applyFilters);

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleString("id-ID");
};
</script>

<template>
    <AppLayout :title="$t('Knowledge articles')">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t("Knowledge articles") }}
                </h2>
                <BaseButton
                    v-if="can('knowledge-articles.create')"
                    :href="route('admin.knowledge.articles.create')"
                >
                    {{ $t("New knowledge article") }}
                </BaseButton>
            </div>
        </template>

        <BaseList :items="articles">
            <template #actions>
                <div class="flex flex-col sm:flex-row gap-3 w-full">
                    <div class="w-full sm:w-64">
                        <BaseInput
                            id="knowledge-search"
                            v-model="search"
                            :label="$t('Search')"
                            :placeholder="$t('Search by title')"
                        />
                    </div>
                    <div class="w-full sm:w-44">
                        <label for="knowledge-status" class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Status") }}
                        </label>
                        <BaseSelect id="knowledge-status" v-model="status" :options="statusFilterOptions" />
                    </div>
                </div>
            </template>

            <template #title="{ item }">
                {{ item.title }}
            </template>

            <template #meta_mobile="{ item }">
                <KnowledgeStatusBadge :status="item.status" />
            </template>

            <template #right="{ item }">
                <KnowledgeStatusBadge :status="item.status" />
                <span class="text-xs text-text-muted">{{ formatDate(item.updated_at) }}</span>
                <Link
                    :href="route('admin.knowledge.articles.edit', item.id)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{ $t("Open") }}
                </Link>
            </template>

            <template #actions_mobile="{ item }">
                <span class="text-xs text-text-muted mr-auto">{{ formatDate(item.updated_at) }}</span>
                <BaseButton
                    :href="route('admin.knowledge.articles.edit', item.id)"
                    variant="secondary"
                    size="sm"
                >
                    {{ $t("Open") }}
                </BaseButton>
            </template>

            <template #pagination>
                <BasePagination :links="articles.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
