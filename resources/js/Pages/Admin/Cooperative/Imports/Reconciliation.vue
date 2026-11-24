<script setup>
import { ref, watch } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    batch: { type: Object, required: true },
    summary: { type: Object, required: true },
    psn_readiness: { type: Object, required: true },
    category_labels: { type: Object, required: true },
    outcome_labels: { type: Object, required: true },
    items: Object,
    inspectedPsn: { type: Object, default: null },
    filters: Object,
    canManage: Boolean,
});

const category = ref(props.filters.category || "");
const status = ref(props.filters.status || "");
const sourceRow = ref(props.filters.source_row || "");
const psnReview = ref(props.filters.psn_review || false);
const profileWarning = ref(props.filters.profile_warning || false);
const duplicateWarning = ref(props.filters.duplicate_warning || false);
const selectedItem = ref(null);

const reload = () => {
    router.get(
        route("admin.cooperative.imports.reconciliation.index", props.batch.id),
        {
            category: category.value,
            status: status.value,
            source_row: sourceRow.value || undefined,
            psn_review: psnReview.value || undefined,
            profile_warning: profileWarning.value || undefined,
            duplicate_warning: duplicateWarning.value || undefined,
            inspect: props.filters.inspect,
        },
        { preserveState: true, replace: true },
    );
};

watch([category, status, psnReview, profileWarning, duplicateWarning], reload);

const resolveForm = useForm({
    outcome: "",
    administrative_note: "",
    email: "",
    phone: "",
    address: "",
});

const psnForm = useForm({
    outcome: "manual_reconciliation",
    units: "",
    share_reference: "",
    administrative_note: "",
});

const openResolve = (item) => {
    selectedItem.value = item;
    resolveForm.reset();
    resolveForm.outcome = item.available_actions?.[0]?.value || "";
};

const submitResolve = () => {
    if (!selectedItem.value) return;
    resolveForm.post(
        route("admin.cooperative.imports.reconciliation.resolveItem", [props.batch.id, selectedItem.value.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedItem.value = null;
            },
        },
    );
};

const submitPsn = () => {
    if (!props.inspectedPsn?.row?.id) return;
    psnForm.post(
        route("admin.cooperative.imports.reconciliation.resolvePsn", [props.batch.id, props.inspectedPsn.row.id]),
        { preserveScroll: true },
    );
};

const inspectPsn = (rowId) => {
    selectedItem.value = null;
    router.get(
        route("admin.cooperative.imports.reconciliation.index", props.batch.id),
        {
            category: category.value,
            status: status.value,
            source_row: sourceRow.value || undefined,
            psn_review: psnReview.value || undefined,
            profile_warning: profileWarning.value || undefined,
            duplicate_warning: duplicateWarning.value || undefined,
            inspect: rowId,
        },
        { preserveState: true },
    );
};

const categoryCount = (key) => props.summary.category_counts?.[key] ?? 0;
</script>

<template>
    <AppLayout :title="$t('Perlu Validasi')">
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t("Perlu Validasi") }} — Batch #{{ batch.id }}
                </h2>
                <p class="text-sm text-text-muted mt-1">
                    {{ batch.status }} · {{ $t("Post-import reconciliation") }}
                </p>
                <Link
                    :href="route('admin.cooperative.imports.show', batch.id)"
                    class="text-sm text-primary hover:underline mt-2 inline-block"
                >
                    {{ $t("Back to legacy imports") }}
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <BaseCard>
                <template #header>{{ $t("Reconciliation summary") }}</template>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-4">
                    <div>
                        <dt class="text-text-muted">{{ $t("Unique rows with review") }}</dt>
                        <dd class="font-medium">{{ summary.unique_rows_with_review }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Total review flags") }}</dt>
                        <dd class="font-medium">{{ summary.total_review_flags }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Resolved flags") }}</dt>
                        <dd class="font-medium">{{ summary.resolved_flags }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Remaining unique rows") }}</dt>
                        <dd class="font-medium">{{ summary.unique_rows_remaining }}</dd>
                    </div>
                </dl>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                    <div v-for="(labels, key) in category_labels" :key="key">
                        <dt class="text-text-muted">{{ labels.label_id }} / {{ labels.label_en }}</dt>
                        <dd class="font-medium">{{ categoryCount(key) }}</dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard v-if="selectedItem && canManage">
                <template #header>{{ $t("Resolve review item") }}</template>
                <p class="text-sm text-text-muted mb-3">
                    Row {{ selectedItem.source_row_number }} · {{ selectedItem.category_label }} ·
                    {{ selectedItem.warning_code }}
                </p>
                <form class="space-y-3" @submit.prevent="submitResolve">
                    <div>
                        <label class="block text-sm mb-1">{{ $t("Outcome") }}</label>
                        <select v-model="resolveForm.outcome" class="rounded border-border w-full">
                            <option
                                v-for="action in selectedItem.available_actions || []"
                                :key="action.value"
                                :value="action.value"
                            >
                                {{ action.label_id }} / {{ action.label_en }}
                            </option>
                        </select>
                    </div>
                    <BaseInput
                        v-if="resolveForm.outcome === 'corrected' && selectedItem.warning_code.includes('email')"
                        v-model="resolveForm.email"
                        :label="$t('Email')"
                    />
                    <BaseInput
                        v-if="resolveForm.outcome === 'corrected' && selectedItem.warning_code.includes('phone')"
                        v-model="resolveForm.phone"
                        :label="$t('Phone')"
                    />
                    <BaseInput
                        v-if="resolveForm.outcome === 'corrected' && selectedItem.warning_code === 'missing_address'"
                        v-model="resolveForm.address"
                        :label="$t('Address')"
                    />
                    <BaseTextarea v-model="resolveForm.administrative_note" :label="$t('Review notes')" />
                    <div class="flex gap-2">
                        <BaseButton type="submit" :disabled="resolveForm.processing">{{ $t("Save decision") }}</BaseButton>
                        <BaseButton type="button" variant="secondary" @click="selectedItem = null">{{ $t("Cancel") }}</BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Review list") }}</template>
                <div class="flex flex-wrap gap-3 mb-4 text-sm">
                    <select v-model="category" class="rounded border-border px-2 py-1">
                        <option value="">{{ $t("All categories") }}</option>
                        <option v-for="(labels, key) in category_labels" :key="key" :value="key">
                            {{ labels.label_id }}
                        </option>
                    </select>
                    <select v-model="status" class="rounded border-border px-2 py-1">
                        <option value="">{{ $t("All statuses") }}</option>
                        <option value="pending">{{ $t("Pending") }}</option>
                        <option value="resolved">{{ $t("Resolved") }}</option>
                    </select>
                    <label class="inline-flex items-center gap-1">
                        <input v-model="psnReview" type="checkbox" />
                        PSN
                    </label>
                    <label class="inline-flex items-center gap-1">
                        <input v-model="profileWarning" type="checkbox" />
                        {{ $t("Profile warning") }}
                    </label>
                    <label class="inline-flex items-center gap-1">
                        <input v-model="duplicateWarning" type="checkbox" />
                        {{ $t("Duplicate warning") }}
                    </label>
                    <div class="flex items-center gap-2">
                        <BaseInput v-model="sourceRow" type="number" min="1" placeholder="Row #" class="w-24" />
                        <BaseButton size="sm" variant="secondary" @click="reload">{{ $t("Filter") }}</BaseButton>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-text-muted border-b border-border">
                                <th class="py-2 pr-3">{{ $t("Source row") }}</th>
                                <th class="py-2 pr-3">{{ $t("Category") }}</th>
                                <th class="py-2 pr-3">{{ $t("Warning") }}</th>
                                <th class="py-2 pr-3">Member</th>
                                <th class="py-2 pr-3">{{ $t("Status") }}</th>
                                <th class="py-2 pr-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items.data" :key="item.id" class="border-b border-border align-top">
                                <td class="py-2 pr-3">{{ item.source_row_number }}</td>
                                <td class="py-2 pr-3">{{ item.category_label }}</td>
                                <td class="py-2 pr-3">{{ item.warning_code }}</td>
                                <td class="py-2 pr-3">
                                    <span v-if="item.member">{{ item.member.legal_name }}</span>
                                    <span v-else class="text-text-muted">—</span>
                                </td>
                                <td class="py-2 pr-3">{{ item.status }}</td>
                                <td class="py-2 pr-3 space-x-2">
                                    <button
                                        v-if="item.psn_review"
                                        type="button"
                                        class="text-primary hover:underline"
                                        @click="inspectPsn(item.import_row_id)"
                                    >
                                        PSN
                                    </button>
                                    <button
                                        v-if="canManage && item.status === 'pending' && !item.psn_review"
                                        type="button"
                                        class="text-primary hover:underline"
                                        @click="openResolve(item)"
                                    >
                                        {{ $t("Decide") }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <BasePagination v-if="items.links" :links="items.links" class="mt-4" />
            </BaseCard>

            <BaseCard v-if="inspectedPsn">
                <template #header>{{ $t("PSN reconciliation") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm mb-4">
                    <div>
                        <dt class="text-text-muted">Member</dt>
                        <dd>{{ inspectedPsn.member?.legal_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Source row") }}</dt>
                        <dd>{{ inspectedPsn.row.source_row_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Share reference") }}</dt>
                        <dd>{{ inspectedPsn.psn_context.share_reference || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Historical principal amount") }}</dt>
                        <dd>{{ inspectedPsn.psn_context.principal_amount ?? "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Historical voluntary amount") }}</dt>
                        <dd>{{ inspectedPsn.psn_context.voluntary_amount ?? "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Computed total") }}</dt>
                        <dd>{{ inspectedPsn.psn_context.computed_total ?? "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Nominal per share") }}</dt>
                        <dd>{{ inspectedPsn.psn_context.unit_value ?? "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Failure reason") }}</dt>
                        <dd>{{ inspectedPsn.psn_context.failure_codes?.join(", ") || "—" }}</dd>
                    </div>
                </dl>

                <form v-if="canManage" class="space-y-3" @submit.prevent="submitPsn">
                    <div>
                        <label class="block text-sm mb-1">{{ $t("Outcome") }}</label>
                        <select v-model="psnForm.outcome" class="rounded border-border w-full">
                            <option value="manual_reconciliation">{{ $t("Confirm units") }} / Konfirmasi unit</option>
                            <option value="confirmed_no_psn_participation">
                                {{ $t("Confirm no participation") }} / Konfirmasi tanpa partisipasi
                            </option>
                            <option value="unresolved_requires_followup">
                                {{ $t("Requires follow-up") }} / Perlu tindak lanjut
                            </option>
                        </select>
                    </div>
                    <BaseInput
                        v-if="psnForm.outcome === 'manual_reconciliation'"
                        v-model="psnForm.units"
                        type="number"
                        min="0"
                        step="1"
                        :label="$t('Resolved units')"
                    />
                    <BaseInput
                        v-if="psnForm.outcome === 'manual_reconciliation'"
                        v-model="psnForm.share_reference"
                        :label="$t('Share reference')"
                    />
                    <BaseTextarea v-model="psnForm.administrative_note" :label="$t('Review notes')" />
                    <BaseButton type="submit" :disabled="psnForm.processing">{{ $t("Save PSN decision") }}</BaseButton>
                </form>
            </BaseCard>
        </div>
    </AppLayout>
</template>
