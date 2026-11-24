<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

defineProps({
    dashboard: { type: Object, required: true },
    canManage: Boolean,
});
</script>

<template>
    <AppLayout :title="$t('Cooperative dashboard')">
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t("Cooperative dashboard") }}
                </h2>
                <p class="text-sm text-text-muted mt-1">
                    {{ $t("Cooperative dashboard intro") }}
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <BaseCard v-if="dashboard.attention?.length">
                <template #header>{{ $t("Needs attention") }}</template>
                <ul class="space-y-3">
                    <li
                        v-for="item in dashboard.attention"
                        :key="item.key"
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-md border border-border p-3"
                    >
                        <div>
                            <p class="font-medium text-text-main">{{ item.label_id }}</p>
                            <p class="text-xs text-text-muted">{{ item.label_en }} · {{ item.count }}</p>
                        </div>
                        <BaseButton :href="item.href" size="sm">{{ $t("Open queue") }}</BaseButton>
                    </li>
                </ul>
            </BaseCard>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <BaseCard>
                    <p class="text-sm text-text-muted">{{ $t("Active Cooperative members") }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ dashboard.active_cooperative_members }}</p>
                </BaseCard>
                <BaseCard>
                    <p class="text-sm text-text-muted">{{ $t("Resolved PSN participants") }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ dashboard.resolved_psn_participants }}</p>
                    <p class="text-xs text-text-muted mt-1">{{ $t("Resolved ledger only") }}</p>
                </BaseCard>
                <BaseCard>
                    <p class="text-sm text-text-muted">{{ $t("Resolved PSN units") }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ dashboard.resolved_psn_units }}</p>
                    <p class="text-xs text-text-muted mt-1">
                        {{ $t("Nominal per share") }}: {{ dashboard.current_unit_value_label }}
                    </p>
                </BaseCard>
                <BaseCard>
                    <p class="text-sm text-text-muted">{{ $t("Recorded participation value") }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ dashboard.resolved_participation_value_label }}</p>
                    <p class="text-xs text-text-muted mt-1">{{ $t("Not final org total while PSN reconciliation incomplete") }}</p>
                </BaseCard>
                <BaseCard>
                    <p class="text-sm text-text-muted">{{ $t("Pending PSN reconciliation") }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ dashboard.pending_psn_reconciliation_rows }}</p>
                </BaseCard>
                <BaseCard>
                    <p class="text-sm text-text-muted">{{ $t("Members with pending review") }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ dashboard.unique_members_with_pending_review }}</p>
                </BaseCard>
            </div>

            <BaseCard>
                <template #header>{{ $t("Quick actions") }}</template>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <BaseButton :href="route('admin.cooperative.memberships.index')" variant="secondary">
                        {{ $t("Cooperative members") }}
                    </BaseButton>
                    <BaseButton :href="route('admin.cooperative.psn.index')" variant="secondary">
                        {{ $t("PSN") }} / {{ $t("shares") }}
                    </BaseButton>
                    <BaseButton
                        v-if="dashboard.latest_import"
                        :href="route('admin.cooperative.imports.reconciliation.index', dashboard.latest_import.id)"
                        variant="secondary"
                    >
                        {{ $t("Perlu Validasi") }}
                    </BaseButton>
                    <BaseButton :href="route('admin.cooperative.imports.index')" variant="secondary">
                        {{ $t("Legacy Cooperative import") }}
                    </BaseButton>
                    <BaseButton :href="route('admin.cooperative.memberships.index', { search: '' })" variant="secondary">
                        {{ $t("Search members") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Recent activity") }}</template>
                <ul v-if="dashboard.recent_activity?.length" class="divide-y divide-border">
                    <li
                        v-for="(item, index) in dashboard.recent_activity"
                        :key="index"
                        class="py-3 text-sm"
                    >
                        <p class="font-medium">{{ item.label_id }}</p>
                        <p v-if="item.imported" class="text-xs text-text-muted">{{ $t("Imported historical activity") }}</p>
                        <p class="text-text-muted">
                            <span v-if="item.member_name">{{ item.member_name }} · </span>
                            <span v-if="item.detail">{{ item.detail }} · </span>
                            <span>{{ item.timestamp }}</span>
                        </p>
                    </li>
                </ul>
                <p v-else class="text-sm text-text-muted">{{ $t("No recent Cooperative activity") }}</p>
            </BaseCard>

            <p v-if="dashboard.latest_import" class="text-sm text-text-muted">
                {{ $t("Latest import") }}:
                <Link
                    :href="route('admin.cooperative.imports.show', dashboard.latest_import.id)"
                    class="text-primary hover:underline"
                >
                    #{{ dashboard.latest_import.id }} · {{ dashboard.latest_import.status }}
                </Link>
            </p>
        </div>
    </AppLayout>
</template>
