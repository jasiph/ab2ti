<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import CooperativeBadge from "@/Components/Cooperative/CooperativeBadge.vue";

const props = defineProps({
    membership: Object,
    canManage: Boolean,
    canAdjustPsn: Boolean,
    psnAdjustmentBlock: { type: String, default: null },
    psnAdjustmentResult: { type: Object, default: null },
    listQuery: { type: Object, default: () => ({}) },
});

const formatDate = (value) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString("id-ID");
};

const entryLabel = (entry) => {
    const map = {
        legacy_opening: "Legacy opening",
        manual_reconciliation: "Manual reconciliation",
        administrative_adjustment: "Administrative adjustment",
        correction_adjustment: "Correction adjustment",
        import_reversal: "Import reversal",
        opening_balance: "Opening",
        adjustment: "Adjustment",
    };
    return map[entry.label] || entry.entry_type;
};

const listHref = computed(() =>
    route("admin.cooperative.memberships.index", {
        search: props.listQuery?.search || undefined,
        status: props.listQuery?.status || undefined,
        psn: props.listQuery?.psn || undefined,
        review: props.listQuery?.review || undefined,
        page: props.listQuery?.page || undefined,
    }),
);

const deltaLabel = (value) => {
    const n = Number(value) || 0;
    return `${n > 0 ? "+" : ""}${n}`;
};

const psnUnresolved = computed(() => props.membership.psn?.state === "unresolved" || props.membership.psn?.reconciliation_pending);
const psnConfirmedNone = computed(() => props.membership.psn?.state === "confirmed_none");
</script>

<template>
    <AppLayout :title="$t('Cooperative membership detail')">
        <template #header>
            <div class="flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-xl text-text-main leading-tight">
                            {{ membership.member?.legal_name }}
                        </h2>
                        <p class="text-sm text-text-muted mt-1 font-mono">
                            {{ membership.member?.membership_number }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <CooperativeBadge :state="membership.status" />
                        <span
                            class="text-xs rounded px-2 py-1"
                            :class="membership.member?.has_user_account ? 'bg-emerald-100 text-emerald-900' : 'bg-surface-alt text-text-muted'"
                        >
                            {{ membership.member?.has_user_account ? $t("User linked") : $t("No User account") }}
                        </span>
                    </div>
                </div>
                <div v-if="membership.review?.badges?.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="badge in membership.review.badges"
                        :key="badge.key"
                        class="text-xs rounded bg-amber-100 text-amber-900 px-2 py-1"
                    >
                        {{ badge.label_id }}
                    </span>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-3xl">
            <BaseCard v-if="psnAdjustmentResult">
                <template #header>{{ $t("PSN share change recorded.") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Before") }}</dt>
                        <dd class="font-semibold">{{ psnAdjustmentResult.previous_units }} {{ $t("shares") }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Change") }}</dt>
                        <dd class="font-semibold font-mono">{{ deltaLabel(psnAdjustmentResult.delta_units) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Now") }}</dt>
                        <dd class="font-semibold">{{ psnAdjustmentResult.resulting_units }} {{ $t("shares") }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Effective date") }}</dt>
                        <dd>{{ formatDate(psnAdjustmentResult.effective_date) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-text-muted">{{ $t("Reason") }}</dt>
                        <dd>{{ $t("psn_reason_" + psnAdjustmentResult.reason) }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Current Cooperative position") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Status") }}</dt>
                        <dd class="mt-1"><CooperativeBadge :state="membership.status" /></dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("User account") }}</dt>
                        <dd class="mt-1">
                            {{ membership.member?.has_user_account ? membership.member?.user_name : $t("No User account") }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Current PSN shares") }}</dt>
                        <dd class="mt-1 text-xl font-semibold">
                            <span v-if="psnUnresolved">{{ $t("PSN unresolved") }}</span>
                            <span v-else-if="psnConfirmedNone">{{ $t("Confirmed no PSN participation") }}</span>
                            <span v-else>{{ membership.psn?.units }} {{ $t("shares") }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Recorded participation value") }}</dt>
                        <dd class="mt-1 font-medium">
                            <span v-if="psnUnresolved">{{ $t("Not available until PSN is reconciled") }}</span>
                            <span v-else>{{ membership.psn?.value_label || "—" }}</span>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-text-muted">{{ $t("Reconciliation status") }}</dt>
                        <dd class="mt-1">
                            {{ psnUnresolved ? $t("PSN needs reconciliation") : $t("No pending PSN reconciliation") }}
                        </dd>
                    </div>
                </dl>
                <div v-if="canManage" class="mt-4 flex flex-col sm:flex-row gap-3">
                    <BaseButton
                        v-if="canAdjustPsn"
                        :href="route('admin.cooperative.memberships.psn.edit', membership.id)"
                        class="min-h-12"
                    >
                        {{ $t("Manage PSN shares") }}
                    </BaseButton>
                    <BaseButton
                        v-else-if="psnUnresolved && membership.review?.reconciliation_batch_id"
                        :href="route('admin.cooperative.imports.reconciliation.index', {
                            batch: membership.review.reconciliation_batch_id,
                            member: membership.member.id,
                            psn_review: 1,
                            status: 'pending',
                        })"
                        class="min-h-12"
                    >
                        {{ $t("Complete reconciliation") }}
                    </BaseButton>
                    <p v-else-if="psnAdjustmentBlock === 'inactive'" class="text-sm text-text-muted">
                        {{ $t("Ordinary PSN share adjustment is unavailable while Cooperative membership is inactive.") }}
                    </p>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Contact summary") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Email") }}</dt>
                        <dd class="mt-1">{{ membership.member?.email || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Phone") }}</dt>
                        <dd class="mt-1">{{ membership.member?.phone || "—" }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <BaseButton
                        :href="route('admin.membership.members.show', membership.member.id)"
                        variant="secondary"
                        size="sm"
                    >
                        {{ $t("Open AB2TI member") }}
                    </BaseButton>
                    <BaseButton
                        v-if="canManage"
                        :href="route('admin.cooperative.memberships.edit', membership.id)"
                        variant="secondary"
                        size="sm"
                    >
                        {{ $t("Edit") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Cooperative membership details") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Joined at") }}</dt>
                        <dd class="mt-1">{{ formatDate(membership.joined_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Ended at") }}</dt>
                        <dd class="mt-1">{{ formatDate(membership.ended_at) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-text-muted">{{ $t("Private staff notes") }}</dt>
                        <dd class="mt-1 whitespace-pre-wrap">{{ membership.notes || "—" }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Historical source of participation") }}</template>
                <p class="text-sm text-text-muted mb-3">
                    {{ $t("Historical Cooperative funds help") }}
                </p>
                <p class="text-xs rounded border border-border bg-surface-alt p-3 mb-4 text-text-muted">
                    {{ $t("Historical funds are not added to current PSN value") }}
                </p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Historical principal source") }}</dt>
                        <dd class="mt-1">{{ membership.historical_funds?.principal?.amount_label || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Historical voluntary source") }}</dt>
                        <dd class="mt-1">{{ membership.historical_funds?.voluntary?.amount_label || "—" }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard v-if="membership.review?.pending">
                <template #header>{{ $t("Review / reconciliation") }}</template>
                <ul class="space-y-2 text-sm">
                    <li v-for="badge in membership.review.badges" :key="badge.key">
                        {{ badge.label_id }} / {{ badge.label_en }}
                    </li>
                </ul>
                <div v-if="membership.review.reconciliation_batch_id" class="mt-4">
                    <BaseButton
                        :href="route('admin.cooperative.imports.reconciliation.index', {
                            batch: membership.review.reconciliation_batch_id,
                            member: membership.member.id,
                        })"
                        variant="secondary"
                        size="sm"
                    >
                        {{ $t("Open reconciliation") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("PSN participation history") }}</template>
                <ul v-if="membership.psn?.history?.length" class="divide-y divide-border">
                    <li
                        v-for="entry in membership.psn.history"
                        :key="entry.id"
                        class="py-3 text-sm"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="font-medium">
                                {{ $t(entryLabel(entry)) }}
                                <span class="font-mono">({{ entry.units > 0 ? "+" : "" }}{{ entry.units }})</span>
                            </p>
                            <p class="text-text-muted">
                                {{ formatDate(entry.effective_date_start) }}
                                · {{ entry.unit_value_label }}
                                <span v-if="entry.share_reference"> · {{ entry.share_reference }}</span>
                            </p>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-text-muted">{{ $t("No PSN history yet") }}</p>
            </BaseCard>

            <Link
                :href="listHref"
                class="text-sm text-primary hover:underline"
            >
                {{ $t("Back to Cooperative members") }}
            </Link>
        </div>
    </AppLayout>
</template>
