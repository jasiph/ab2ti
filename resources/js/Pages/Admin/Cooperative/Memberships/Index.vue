<script setup>
import { computed, ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import CooperativeBadge from "@/Components/Cooperative/CooperativeBadge.vue";

const props = defineProps({
    memberships: Object,
    filters: Object,
    canManage: Boolean,
});

const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");
const psn = ref(props.filters.psn || "");
const review = ref(props.filters.review || "");

const applyFilters = () => {
    router.get(
        route("admin.cooperative.memberships.index"),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            psn: psn.value || undefined,
            review: review.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

let timeout = null;
watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 300);
});
watch([status, psn, review], applyFilters);

const listQuery = computed(() => ({
    search: props.filters.search || undefined,
    status: props.filters.status || undefined,
    psn: props.filters.psn || undefined,
    review: props.filters.review || undefined,
}));

const memberHref = (item) =>
    route("admin.cooperative.memberships.show", {
        membership: item.id,
        ...listQuery.value,
    });

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString("id-ID");
};
</script>

<template>
    <AppLayout :title="$t('Cooperative members')">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-text-main leading-tight">
                        {{ $t("Cooperative members") }}
                    </h2>
                    <Link
                        :href="route('admin.cooperative.dashboard')"
                        class="text-sm text-primary hover:underline"
                    >
                        {{ $t("Cooperative dashboard") }}
                    </Link>
                </div>
                <BaseButton
                    v-if="canManage"
                    :href="route('admin.cooperative.memberships.create')"
                >
                    {{ $t("Establish Cooperative membership") }}
                </BaseButton>
            </div>
        </template>

        <BaseList :items="memberships">
            <template #header>
                <p class="text-sm text-text-muted">
                    {{ $t("Cooperative members intro") }}
                </p>
            </template>
            <template #actions>
                <div class="flex flex-col gap-3 w-full">
                    <div class="w-full sm:w-80">
                        <BaseInput
                            id="cooperative-search"
                            v-model="search"
                            :label="$t('Search')"
                            :placeholder="$t('Name, email, phone, or record identifier')"
                        />
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <label class="block text-sm flex-1">
                            <span class="text-text-muted">{{ $t("Status") }}</span>
                            <select
                                v-model="status"
                                class="mt-1 w-full border-border bg-surface text-text-main rounded-md shadow-sm min-h-control text-sm"
                            >
                                <option value="">{{ $t("All statuses") }}</option>
                                <option value="active">{{ $t("Active") }}</option>
                                <option value="inactive">{{ $t("Inactive") }}</option>
                            </select>
                        </label>
                        <label class="block text-sm flex-1">
                            <span class="text-text-muted">PSN</span>
                            <select
                                v-model="psn"
                                class="mt-1 w-full border-border bg-surface text-text-main rounded-md shadow-sm min-h-control text-sm"
                            >
                                <option value="">{{ $t("All PSN") }}</option>
                                <option value="has_psn">{{ $t("PSN participant") }}</option>
                                <option value="no_psn">{{ $t("No resolved PSN") }}</option>
                            </select>
                        </label>
                        <label class="block text-sm flex-1">
                            <span class="text-text-muted">{{ $t("Review") }}</span>
                            <select
                                v-model="review"
                                class="mt-1 w-full border-border bg-surface text-text-main rounded-md shadow-sm min-h-control text-sm"
                            >
                                <option value="">{{ $t("All reviews") }}</option>
                                <option value="pending">{{ $t("Review pending") }}</option>
                                <option value="psn_pending">{{ $t("PSN reconciliation pending") }}</option>
                                <option value="profile_incomplete">{{ $t("Profile incomplete") }}</option>
                            </select>
                        </label>
                    </div>
                </div>
            </template>

            <template #title="{ item }">
                {{ item.member?.legal_name }}
            </template>
            <template #description="{ item }">
                <span class="font-mono text-xs">{{ item.member?.membership_number }}</span>
                <span class="hidden sm:inline text-border">·</span>
                <span class="hidden sm:inline">{{ item.member?.email || "—" }}</span>
            </template>
            <template #meta_mobile="{ item }">
                <div class="flex flex-wrap gap-2 items-center">
                    <CooperativeBadge :state="item.status" />
                    <span v-if="item.psn_state === 'unresolved'" class="text-xs font-medium text-amber-800">{{ $t("PSN unresolved") }}</span>
                    <span v-else-if="item.psn_state === 'confirmed_none'" class="text-xs font-medium">{{ $t("Confirmed no PSN participation") }}</span>
                    <span v-else class="text-xs font-medium">PSN: {{ item.psn_units }}</span>
                    <span
                        v-if="item.review_pending"
                        class="text-xs rounded bg-amber-100 text-amber-900 px-2 py-0.5"
                    >
                        {{ $t("Review") }}
                    </span>
                </div>
            </template>
            <template #right="{ item }">
                <CooperativeBadge :state="item.status" />
                <span v-if="item.psn_state === 'unresolved'" class="text-xs font-medium text-amber-800">{{ $t("PSN unresolved") }}</span>
                <span v-else-if="item.psn_state === 'confirmed_none'" class="text-xs font-medium">{{ $t("Confirmed no PSN participation") }}</span>
                <span v-else class="text-xs font-medium">PSN {{ item.psn_units }}</span>
                <span
                    v-if="item.review_pending"
                    class="text-xs rounded bg-amber-100 text-amber-900 px-2 py-0.5"
                >
                    {{ $t("Review") }}
                </span>
                <span class="text-xs text-text-muted">{{ formatDate(item.joined_at) }}</span>
                <Link
                    :href="memberHref(item)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{ $t("Open") }}
                </Link>
            </template>
            <template #actions_mobile="{ item }">
                <span class="text-xs text-text-muted mr-auto">{{ formatDate(item.joined_at) }}</span>
                <BaseButton
                    :href="memberHref(item)"
                    variant="secondary"
                    size="sm"
                >
                    {{ $t("Open") }}
                </BaseButton>
            </template>
            <template #pagination>
                <BasePagination :links="memberships.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
