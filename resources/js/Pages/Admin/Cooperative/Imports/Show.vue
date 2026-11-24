<script setup>
import { ref, watch } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import axios from "axios";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    batch: { type: Object, required: true },
    rows: Object,
    inspectedRow: { type: Object, default: null },
    filters: Object,
    canManage: Boolean,
});

const status = ref(props.filters.status || "");
const matchingRow = ref(null);
const memberSearch = ref("");
const members = ref([]);
const confirmCommit = ref(false);
const confirmReverse = ref(false);
const replaceLegacy = ref(false);
const replaceAcknowledged = ref(false);
const matchForm = useForm({
    member_id: "",
    review_notes: "",
});
const commitForm = useForm({
    confirm: false,
    replace_legacy: false,
    replace_acknowledged: false,
});
const defaultWorksheet = (() => {
    const worksheets = props.batch.worksheets || [];
    const selected = props.batch.worksheet_name;
    if (selected) {
        return selected;
    }
    const visibleCandidates = worksheets.filter((sheet) => sheet.candidate && sheet.visible);
    if (visibleCandidates.length === 1) {
        return visibleCandidates[0].name;
    }
    const anyCandidate = worksheets.find((sheet) => sheet.candidate);
    return anyCandidate?.name || "";
})();
const worksheetForm = useForm({
    worksheet_name: defaultWorksheet,
});
const submitWorksheet = () => {
    worksheetForm.post(route("admin.cooperative.imports.selectWorksheet", props.batch.id));
};

watch(status, (value) => {
    router.get(
        route("admin.cooperative.imports.show", props.batch.id),
        { status: value, inspect: props.filters.inspect },
        { preserveState: true, replace: true },
    );
});

let timeout = null;
watch(memberSearch, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(async () => {
        if (!value) {
            members.value = [];
            return;
        }
        const response = await axios.get(route("admin.cooperative.imports.members"), {
            params: { search: value },
        });
        members.value = response.data;
    }, 300);
});

watch(replaceLegacy, (value) => {
    if (!value) {
        replaceAcknowledged.value = false;
    }
});

const openMatch = (row) => {
    matchingRow.value = row.id;
    matchForm.member_id = "";
    matchForm.review_notes = row.review_notes || "";
    memberSearch.value = "";
    members.value = [];
};

const selectMember = (member) => {
    matchForm.member_id = member.id;
};

const submitMatch = (row) => {
    matchForm.post(route("admin.cooperative.imports.match", [props.batch.id, row.id]), {
        preserveScroll: true,
        onSuccess: () => {
            matchingRow.value = null;
        },
    });
};

const canSubmitCommit = () => {
    if (replaceLegacy.value) {
        return props.batch.can_commit_replace && replaceAcknowledged.value;
    }
    return props.batch.can_commit;
};

const submitCommit = () => {
    if (!canSubmitCommit()) {
        return;
    }
    commitForm.confirm = true;
    commitForm.replace_legacy = replaceLegacy.value;
    commitForm.replace_acknowledged = replaceLegacy.value ? replaceAcknowledged.value : false;
    commitForm.post(route("admin.cooperative.imports.commit", props.batch.id));
};

const reverse = () => {
    router.post(route("admin.cooperative.imports.reverse", props.batch.id));
};

const highlightClass = (row, key) =>
    row.highlights?.includes(key) ? "text-danger font-medium" : "";
</script>

<template>
    <AppLayout :title="$t('Legacy Cooperative import')">
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ batch.original_filename }}
                </h2>
                <p class="text-sm text-text-muted mt-1">{{ batch.status }}</p>
                <div v-if="batch.status === 'completed_with_review'" class="mt-3">
                    <Link
                        :href="route('admin.cooperative.imports.reconciliation.index', batch.id)"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        {{ $t("Perlu Validasi") }} / Post-import reconciliation
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <BaseCard>
                <template #header>{{ $t("Import preview") }}</template>
                <p class="text-sm text-text-muted mb-3">
                    {{ $t("Preview does not change Cooperative/PSN canonical data.") }}
                </p>
                <p class="text-sm text-text-muted mb-3">
                    {{ $t("Data ini merupakan migrasi anggota AB2TI yang sudah ada secara historis, bukan pendaftaran anggota baru.") }}
                </p>
                <p class="text-sm text-text-muted mb-3">
                    {{ $t("Import ini tidak membuat akun login/User.") }}
                </p>
                <p v-if="batch.duplicate_of_batch_id" class="text-sm text-danger mb-3">
                    {{ $t("Duplicate file warning") }} (#{{ batch.duplicate_of_batch_id }})
                </p>
                <p class="text-xs font-mono text-text-muted mb-3 break-all">
                    {{ $t("File hash") }}: {{ batch.file_hash }}
                </p>
                <p v-if="batch.worksheet_name" class="text-sm mb-3">
                    {{ $t("Selected worksheet") }}:
                    <span class="font-medium">{{ batch.worksheet_name }}</span>
                    <span class="text-text-muted">
                        · {{ batch.worksheet_visible === false ? $t("Hidden") : $t("Visible") }}
                        <template v-if="batch.header_row_number">
                            · {{ $t("Header row") }} {{ batch.header_row_number }}
                        </template>
                    </span>
                </p>
                <p v-if="batch.summary?.ignored_footer_or_summary_rows" class="text-sm text-text-muted mb-3">
                    {{ $t("Ignored footer or summary rows") }}:
                    {{ batch.summary.ignored_footer_or_summary_rows }}
                </p>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("Rows total") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.total ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Existing AB2TI members matched") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.existing_members_matched ?? batch.summary?.matched ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Anggota AB2TI yang akan dibuat dari data lama") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_legacy_members ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Profile warning rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.profile_warning_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Source duplicate conflict rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.source_duplicate_conflict_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Member ready rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.member_ready_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Cooperative membership ready rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.cooperative_membership_ready_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Funds ready rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.funds_ready_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("PSN ready rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.psn_ready_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("PSN review rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.psn_review_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Identity review rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.identity_review_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Financial review rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.financial_review_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Share review rows") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.share_review_rows ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Ready") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.ready ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Needs review") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.needs_review ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Invalid") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.invalid ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Proposed Cooperative memberships") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_cooperative_memberships ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Historical principal amount") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_principal_amount ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Historical voluntary amount") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_voluntary_amount ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Proposed PSN participants") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_psn_participants ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Proposed total PSN units") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_total_psn_units ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Explicit-unit count") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_explicit_units ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Inferred-unit count") }}</dt>
                        <dd class="font-medium">{{ batch.summary?.proposed_inferred_units ?? 0 }}</dd>
                    </div>
                </dl>
                <p class="text-sm text-text-muted mt-3">
                    {{ $t("Historical funds are source of PSN participation") }}
                </p>
                <p class="text-sm text-text-muted">
                    {{ $t("NIK is not a Member match key") }}
                </p>
                <div v-if="canManage" class="mt-4 flex flex-wrap gap-2">
                    <div
                        v-if="batch.can_commit || batch.can_commit_replace"
                        class="w-full rounded-md border border-border p-3 space-y-3"
                    >
                        <p class="text-sm font-medium">{{ $t("Import mode") }}</p>
                        <p class="text-sm text-text-muted">{{ $t("Append to existing data") }}</p>
                        <label class="flex items-start gap-3 text-sm">
                            <input
                                v-model="replaceLegacy"
                                type="checkbox"
                                class="mt-1"
                                :disabled="confirmCommit"
                            >
                            <span>
                                <span class="font-medium">{{ $t("Delete previous legacy import data before importing") }}</span>
                                <span class="block text-text-muted mt-1">{{ $t("Replace legacy import help") }}</span>
                            </span>
                        </label>
                        <div
                            v-if="replaceLegacy"
                            class="rounded-md border border-danger/40 bg-danger/5 p-3 space-y-2 text-sm"
                        >
                            <p class="font-medium text-danger">{{ $t("Replace data mode") }}</p>
                            <p>{{ $t("Replace data mode warning") }}</p>
                            <dl class="grid grid-cols-2 gap-2">
                                <div>
                                    <dt class="text-text-muted">{{ $t("Legacy Members eligible for replacement") }}</dt>
                                    <dd class="font-medium">{{ batch.replacement_impact?.eligible_legacy_members ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Legacy Members preserved") }}</dt>
                                    <dd class="font-medium">{{ batch.replacement_impact?.preserved_legacy_members ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Cooperative Memberships") }}</dt>
                                    <dd class="font-medium">{{ batch.replacement_impact?.cooperative_memberships ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Historical fund entries") }}</dt>
                                    <dd class="font-medium">{{ batch.replacement_impact?.historical_fund_entries ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("PSN participation entries") }}</dt>
                                    <dd class="font-medium">{{ batch.replacement_impact?.psn_participation_entries ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Import batches affected") }}</dt>
                                    <dd class="font-medium">{{ batch.replacement_impact?.import_batches_affected ?? 0 }}</dd>
                                </div>
                            </dl>
                            <p class="font-medium mt-2">{{ $t("New source data") }}</p>
                            <dl class="grid grid-cols-2 gap-2">
                                <div>
                                    <dt class="text-text-muted">{{ $t("Rows total") }}</dt>
                                    <dd class="font-medium">{{ batch.summary?.total ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Anggota AB2TI yang akan dibuat dari data lama") }}</dt>
                                    <dd class="font-medium">{{ batch.summary?.proposed_legacy_members ?? batch.summary?.total ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("PSN ready rows") }}</dt>
                                    <dd class="font-medium">{{ batch.summary?.psn_ready_rows ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("PSN review rows") }}</dt>
                                    <dd class="font-medium">{{ batch.summary?.psn_review_rows ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Profile warning rows") }}</dt>
                                    <dd class="font-medium">{{ batch.summary?.profile_warning_rows ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-muted">{{ $t("Source duplicate conflict rows") }}</dt>
                                    <dd class="font-medium">{{ batch.summary?.source_duplicate_conflict_rows ?? 0 }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <BaseButton
                        v-if="(batch.can_commit || batch.can_commit_replace) && !confirmCommit"
                        type="button"
                        @click="confirmCommit = true"
                    >
                        {{ $t("Commit ready rows") }}
                    </BaseButton>
                    <div v-if="confirmCommit" class="w-full rounded-md border border-border p-3 space-y-3">
                        <p class="text-sm">{{ $t("Commit confirmation help") }}</p>
                        <p class="text-sm text-text-muted">
                            {{ $t("Import mode") }}:
                            {{ replaceLegacy ? $t("Replace previous legacy import data") : $t("Append to existing data") }}
                        </p>
                        <p class="text-sm text-text-muted">
                            {{ $t("Anggota AB2TI yang akan dibuat dari data lama") }}:
                            {{ batch.summary?.proposed_legacy_members ?? 0 }}
                        </p>
                        <p class="text-sm text-text-muted">{{ $t("Import ini tidak membuat akun login/User.") }}</p>
                        <label v-if="replaceLegacy" class="flex items-start gap-3 text-sm">
                            <input v-model="replaceAcknowledged" type="checkbox" class="mt-1">
                            <span>{{ $t("I understand previous legacy import data will be replaced") }}</span>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton
                                type="button"
                                :disabled="commitForm.processing || !canSubmitCommit()"
                                @click="submitCommit"
                            >
                                {{ $t("Confirm commit") }}
                            </BaseButton>
                            <BaseButton
                                type="button"
                                variant="secondary"
                                @click="confirmCommit = false; replaceAcknowledged = false"
                            >
                                {{ $t("Cancel") }}
                            </BaseButton>
                        </div>
                    </div>
                    <BaseButton
                        v-if="batch.can_reverse && !confirmReverse"
                        type="button"
                        variant="secondary"
                        @click="confirmReverse = true"
                    >
                        {{ $t("Reverse unmodified batch") }}
                    </BaseButton>
                    <div v-if="confirmReverse" class="w-full rounded-md border border-border p-3 space-y-3">
                        <p class="text-sm">{{ $t("Reverse confirmation help") }}</p>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton type="button" variant="secondary" @click="reverse">
                                {{ $t("Confirm reverse") }}
                            </BaseButton>
                            <BaseButton type="button" variant="secondary" @click="confirmReverse = false">
                                {{ $t("Cancel") }}
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <BaseCard v-if="batch.worksheets?.length">
                <template #header>{{ $t("Workbook worksheets") }}</template>
                <p class="text-sm text-text-muted mb-3">
                    {{ $t("Select one worksheet. Hidden sheets are not imported unless chosen explicitly.") }}
                </p>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="sheet in batch.worksheets"
                        :key="sheet.name"
                        class="rounded-md border border-border p-3"
                    >
                        <label class="flex items-start gap-3">
                            <input
                                v-if="canManage && batch.needs_sheet_selection"
                                v-model="worksheetForm.worksheet_name"
                                type="radio"
                                class="mt-1"
                                :value="sheet.name"
                                :name="'worksheet-' + batch.id"
                            >
                            <span>
                                <span class="font-medium">{{ sheet.name }}</span>
                                <span class="text-text-muted">
                                    · {{ sheet.visible === false ? $t("Hidden") : $t("Visible") }}
                                    · {{ sheet.candidate ? $t("Candidate") : $t("Incompatible") }}
                                    <template v-if="sheet.header_row">
                                        · {{ $t("Header row") }} {{ sheet.header_row }}
                                    </template>
                                    · {{ $t("Estimated source rows") }} {{ sheet.estimated_source_rows ?? 0 }}
                                </span>
                            </span>
                        </label>
                    </li>
                </ul>
                <div v-if="canManage && batch.needs_sheet_selection" class="mt-4">
                    <BaseButton
                        type="button"
                        :disabled="!worksheetForm.worksheet_name || worksheetForm.processing"
                        @click="submitWorksheet"
                    >
                        {{ $t("Parse selected worksheet") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard v-if="inspectedRow">
                <template #header>{{ $t("Row inspect") }} · {{ inspectedRow.source_row_number }}</template>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <h3 class="font-medium mb-2">{{ $t("SOURCE") }}</h3>
                        <p>{{ inspectedRow.source.name }}</p>
                        <p class="text-text-muted">{{ inspectedRow.source.email }} · {{ inspectedRow.source.phone }}</p>
                        <p :class="highlightClass(inspectedRow, 'nik_not_a_match_key')">
                            {{ $t("Identity class") }}: {{ inspectedRow.source.identity_class }}
                            <span v-if="inspectedRow.source.nik_present_not_used_for_match">
                                · {{ $t("NIK is not a Member match key") }}
                            </span>
                        </p>
                        <p>{{ inspectedRow.source.share_raw }}</p>
                        <p>{{ inspectedRow.source.date_raw }}</p>
                    </div>
                    <div>
                        <h3 class="font-medium mb-2">{{ $t("NORMALIZED") }}</h3>
                        <p>{{ inspectedRow.normalized.share_reference }} · {{ inspectedRow.normalized.units }} ({{ inspectedRow.normalized.units_origin }})</p>
                        <p>{{ $t("Computed total") }}: {{ inspectedRow.normalized.computed_total }}</p>
                    </div>
                    <div>
                        <h3 class="font-medium mb-2">{{ $t("MATCHED MEMBER") }}</h3>
                        <p v-if="inspectedRow.matched_member">
                            {{ inspectedRow.matched_member.legal_name }} · {{ inspectedRow.matched_member.membership_number }}
                        </p>
                        <p v-else-if="inspectedRow.normalized.propose_create_member">
                            {{ $t("Anggota AB2TI yang akan dibuat dari data lama") }}
                        </p>
                        <p v-else :class="highlightClass(inspectedRow, 'unmatched_identity')">{{ $t("Unmatched identity") }}</p>
                        <p class="text-text-muted">{{ inspectedRow.match_method || inspectedRow.normalized.member_resolution || "—" }}</p>
                    </div>
                    <div>
                        <h3 class="font-medium mb-2">{{ $t("RECONCILIATION") }}</h3>
                        <p :class="highlightClass(inspectedRow, 'total_mismatch')">
                            {{ $t("Total matches") }}: {{ inspectedRow.reconciliation.total_matches ? $t("Yes") : $t("No") }}
                        </p>
                        <p :class="highlightClass(inspectedRow, 'share_mismatch')">
                            {{ $t("Share reconciled") }}: {{ inspectedRow.reconciliation.share_reconciled ? $t("Yes") : $t("No") }}
                        </p>
                        <p>{{ $t("Historical funds are source of PSN participation") }}</p>
                    </div>
                </div>
            </BaseCard>

            <div>
                <label class="block text-sm mb-3">
                    <span class="text-text-muted">{{ $t("Status") }}</span>
                    <select
                        v-model="status"
                        class="mt-1 w-full sm:w-56 border-border bg-surface text-text-main rounded-md shadow-sm min-h-control text-sm"
                    >
                        <option value="">{{ $t("All statuses") }}</option>
                        <option value="ready">{{ $t("Ready") }}</option>
                        <option value="needs_review">{{ $t("Needs review") }}</option>
                        <option value="invalid">{{ $t("Invalid") }}</option>
                        <option value="committed">{{ $t("Committed") }}</option>
                    </select>
                </label>

                <ul class="space-y-4">
                    <li v-for="row in rows.data" :key="row.id">
                        <BaseCard>
                            <template #header>
                                {{ $t("Source row") }} {{ row.source_row_number }} · {{ row.validation_status }}
                            </template>
                            <p class="text-sm" :class="highlightClass(row, 'unmatched_identity')">
                                {{ row.source?.name }}
                                <span class="text-text-muted"> · {{ row.match_method || row.normalized?.member_resolution || "—" }}</span>
                            </p>
                            <p v-if="row.normalized?.propose_create_member" class="text-sm text-text-muted">
                                {{ $t("Anggota AB2TI yang akan dibuat dari data lama") }}
                            </p>
                            <p v-if="row.matched_member" class="text-sm text-text-muted">
                                {{ row.matched_member.legal_name }} · {{ row.matched_member.membership_number }}
                            </p>
                            <p v-if="row.source?.nik_present_not_used_for_match" class="text-sm text-text-muted">
                                {{ $t("NIK is not a Member match key") }}
                            </p>
                            <ul v-if="row.validation_error_labels?.length" class="text-sm text-danger mt-2 list-disc pl-5">
                                <li v-for="item in row.validation_error_labels" :key="item.code">
                                    {{ item.label }}
                                </li>
                            </ul>
                            <div class="mt-3">
                                <Link
                                    :href="route('admin.cooperative.imports.show', props.batch.id)"
                                    :data="{ status, inspect: row.id }"
                                    class="text-sm text-primary hover:underline"
                                >
                                    {{ $t("Inspect row") }}
                                </Link>
                            </div>
                            <div
                                v-if="canManage && row.validation_status !== 'committed'"
                                class="mt-4 space-y-3"
                            >
                                <BaseButton type="button" variant="secondary" size="sm" @click="openMatch(row)">
                                    {{ $t("Match member manually") }}
                                </BaseButton>
                                <div v-if="matchingRow === row.id" class="space-y-3">
                                    <BaseInput
                                        :id="`member-search-${row.id}`"
                                        v-model="memberSearch"
                                        :label="$t('Search AB2TI members')"
                                    />
                                    <ul v-if="members.length" class="border border-border rounded-md divide-y">
                                        <li v-for="member in members" :key="member.id">
                                            <button
                                                type="button"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-surface-variant/60"
                                                :class="matchForm.member_id === member.id ? 'bg-primary/5' : ''"
                                                @click="selectMember(member)"
                                            >
                                                {{ member.legal_name }} · {{ member.membership_number }}
                                            </button>
                                        </li>
                                    </ul>
                                    <BaseTextarea
                                        :id="`review-notes-${row.id}`"
                                        v-model="matchForm.review_notes"
                                        :label="$t('Review notes')"
                                    />
                                    <BaseButton type="button" size="sm" :disabled="!matchForm.member_id" @click="submitMatch(row)">
                                        {{ $t("Save match") }}
                                    </BaseButton>
                                </div>
                            </div>
                        </BaseCard>
                    </li>
                </ul>
                <div class="mt-4">
                    <BasePagination :links="rows.links" />
                </div>
            </div>

            <Link :href="route('admin.cooperative.imports.index')" class="text-sm text-primary hover:underline">
                {{ $t("Back to legacy imports") }}
            </Link>
        </div>
    </AppLayout>
</template>
