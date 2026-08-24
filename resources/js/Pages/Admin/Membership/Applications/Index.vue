<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import MembershipStatusBadge from "@/Components/Membership/MembershipStatusBadge.vue";

const props = defineProps({
    applications: Object,
    filters: Object,
    statusOptions: Array,
});

const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");

const statusFilterOptions = [
    { value: "", label: "Semua status" },
    ...props.statusOptions.map((value) => ({
        value,
        label: {
            submitted: "Diterima",
            under_review: "Sedang ditinjau",
            revision_requested: "Revisi diminta",
            approved: "Disetujui",
            rejected: "Ditolak",
        }[value] || value,
    })),
];

let timeout = null;
const applyFilters = () => {
    router.get(
        route("admin.membership.applications.index"),
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
    <AppLayout :title="$t('Membership applications')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                {{ $t("Membership applications") }}
            </h2>
        </template>

        <BaseList :items="applications">
            <template #header>
                <p class="text-sm text-text-muted">
                    {{ $t("Review submitted membership applications") }}
                </p>
            </template>
            <template #actions>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <div class="w-full sm:w-64">
                        <BaseInput
                            id="application-search"
                            v-model="search"
                            :label="$t('Search')"
                            :placeholder="$t('Name, email, or reference')"
                        />
                    </div>
                    <div class="w-full sm:w-56">
                        <label for="application-status" class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Status") }}
                        </label>
                        <BaseSelect
                            id="application-status"
                            v-model="status"
                            :options="statusFilterOptions"
                            :placeholder="$t('All statuses')"
                        />
                    </div>
                </div>
            </template>

            <template #title="{ item }">
                {{ item.legal_name }}
            </template>

            <template #description="{ item }">
                <span>{{ item.email }}</span>
                <span class="hidden sm:inline text-border">·</span>
                <span class="font-mono text-xs">{{ item.reference_code }}</span>
            </template>

            <template #meta_mobile="{ item }">
                <MembershipStatusBadge :status="item.status" />
            </template>

            <template #right="{ item }">
                <MembershipStatusBadge :status="item.status" />
                <span class="text-xs text-text-muted">{{ formatDate(item.submitted_at) }}</span>
                <Link
                    :href="route('admin.membership.applications.show', item.id)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{ $t("Open") }}
                </Link>
            </template>

            <template #actions_mobile="{ item }">
                <span class="text-xs text-text-muted mr-auto">{{ formatDate(item.submitted_at) }}</span>
                <BaseButton
                    :href="route('admin.membership.applications.show', item.id)"
                    variant="secondary"
                    size="sm"
                >
                    {{ $t("Open") }}
                </BaseButton>
            </template>

            <template #pagination>
                <BasePagination :links="applications.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
