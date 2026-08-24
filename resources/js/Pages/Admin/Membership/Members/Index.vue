<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import MembershipStatusBadge from "@/Components/Membership/MembershipStatusBadge.vue";

const props = defineProps({
    members: Object,
    filters: Object,
});

const search = ref(props.filters.search || "");

let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route("admin.membership.members.index"),
            { search: value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleString("id-ID");
};
</script>

<template>
    <AppLayout :title="$t('AB2TI members')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                {{ $t("AB2TI members") }}
            </h2>
        </template>

        <BaseList :items="members">
            <template #header>
                <p class="text-sm text-text-muted">
                    {{ $t("Member registry intro") }}
                </p>
            </template>
            <template #actions>
                <div class="w-full sm:w-72">
                    <BaseInput
                        id="member-search"
                        v-model="search"
                        :label="$t('Search')"
                        :placeholder="$t('Name, email, or record identifier')"
                    />
                </div>
            </template>

            <template #title="{ item }">
                {{ item.legal_name }}
            </template>

            <template #description="{ item }">
                <span>{{ item.email }}</span>
                <span class="hidden sm:inline text-border">·</span>
                <span class="font-mono text-xs">{{ item.membership_number }}</span>
            </template>

            <template #meta_mobile="{ item }">
                <MembershipStatusBadge :status="item.status" />
            </template>

            <template #right="{ item }">
                <MembershipStatusBadge :status="item.status" />
                <span class="text-xs text-text-muted">{{ formatDate(item.recognized_at) }}</span>
                <Link
                    :href="route('admin.membership.members.show', item.id)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{ $t("Open") }}
                </Link>
            </template>

            <template #actions_mobile="{ item }">
                <span class="text-xs text-text-muted mr-auto">{{ formatDate(item.recognized_at) }}</span>
                <BaseButton
                    :href="route('admin.membership.members.show', item.id)"
                    variant="secondary"
                    size="sm"
                >
                    {{ $t("Open") }}
                </BaseButton>
            </template>

            <template #pagination>
                <BasePagination :links="members.links" />
            </template>
        </BaseList>
    </AppLayout>
</template>
