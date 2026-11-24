<script setup>
import { computed, ref, watch } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    membership: Object,
    reasons: Array,
    canManage: Boolean,
    confirmationToken: String,
    expectedCurrentUnits: Number,
    maxEffectiveDate: String,
    stalePsn: { type: Object, default: null },
});

const page = usePage();
const step = ref(1);
const currentUnits = computed(() => Number(props.expectedCurrentUnits ?? props.membership.psn?.units ?? 0));
const unitValue = computed(() => props.membership.psn?.unit_value ?? 0);

const form = useForm({
    desired_units: String(currentUnits.value),
    expected_current_units: currentUnits.value,
    confirmation_token: props.confirmationToken,
    effective_date: props.maxEffectiveDate || new Date().toISOString().slice(0, 10),
    reason: "",
    note: "",
    share_reference: props.membership.psn?.share_reference || "",
    confirm: false,
    acknowledge_reduction: false,
    correction_of_entry_id: "",
    membership_id: props.membership.id,
    participation_id: props.membership.psn?.participation_id || "",
    member_id: props.membership.member?.id || "",
});

watch(
    () => props.confirmationToken,
    (token) => {
        form.confirmation_token = token;
        form.expected_current_units = currentUnits.value;
        form.desired_units = String(currentUnits.value);
        form.confirm = false;
        form.acknowledge_reduction = false;
        step.value = 1;
    },
);

const desiredUnits = computed(() => {
    const raw = String(form.desired_units ?? "").trim();
    if (!/^\d+$/.test(raw)) return null;
    return Number(raw);
});
const delta = computed(() => {
    if (desiredUnits.value === null) return null;
    return desiredUnits.value - currentUnits.value;
});
const isReduction = computed(() => delta.value !== null && delta.value < 0);
const beforeValue = computed(() => currentUnits.value * unitValue.value);
const afterValue = computed(() => (desiredUnits.value ?? 0) * unitValue.value);
const canContinue = computed(
    () =>
        desiredUnits.value !== null &&
        delta.value !== 0 &&
        !!form.reason &&
        !!form.effective_date &&
        (!isReduction.value || form.note.trim().length > 0),
);

const goReview = () => {
    if (!canContinue.value || form.processing) return;
    step.value = 2;
    form.confirm = false;
    form.acknowledge_reduction = false;
};

const goEdit = () => {
    step.value = 1;
    form.confirm = false;
    form.acknowledge_reduction = false;
};

const submit = () => {
    if (!form.confirm || form.processing) return;
    if (isReduction.value && !form.acknowledge_reduction) return;
    form
        .transform((data) => ({
            desired_units: String(data.desired_units).trim(),
            expected_current_units: data.expected_current_units,
            confirmation_token: data.confirmation_token,
            effective_date: data.effective_date,
            reason: data.reason,
            note: data.note,
            share_reference: data.share_reference,
            confirm: data.confirm,
            acknowledge_reduction: isReduction.value ? data.acknowledge_reduction : false,
            correction_of_entry_id: data.correction_of_entry_id || null,
            membership_id: data.membership_id || null,
            participation_id: data.participation_id || null,
            member_id: data.member_id || null,
        }))
        .post(route("admin.cooperative.memberships.psn.adjust", props.membership.id), {
            preserveScroll: false,
            preserveState: false,
        });
};

const formatIdr = (value) =>
    new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value || 0);

const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="$t('Manage PSN shares')">
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t("Manage PSN shares") }}
                </h2>
                <p class="text-sm text-text-muted mt-1">
                    {{ membership.member?.legal_name }} · {{ membership.member?.membership_number }}
                </p>
            </div>
        </template>

        <div class="space-y-6 max-w-xl mx-auto">
            <BaseCard v-if="stalePsn || flashError" class="border-danger">
                <template #header>{{ $t("PSN data changed") }}</template>
                <p class="text-sm mb-3">{{ flashError || $t("PSN shares changed since this page was opened. Review the latest share count before confirming.") }}</p>
                <dl v-if="stalePsn" class="text-sm space-y-2">
                    <div class="flex justify-between gap-3">
                        <dt class="text-text-muted">{{ $t("Units you reviewed earlier") }}</dt>
                        <dd class="font-semibold">{{ stalePsn.reviewed_units }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-text-muted">{{ $t("Latest share count") }}</dt>
                        <dd class="font-semibold">{{ stalePsn.current_units }}</dd>
                    </div>
                </dl>
                <p class="text-sm text-text-muted mt-3">{{ $t("Start the review again from the latest share count.") }}</p>
            </BaseCard>

            <BaseCard v-if="step === 1">
                <template #header>{{ $t("Step 1 edit") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("PSN adjustment edit help") }}
                </p>
                <dl class="text-sm mb-4 space-y-2">
                    <div class="flex justify-between gap-3">
                        <dt class="text-text-muted">{{ $t("Current shares") }}</dt>
                        <dd class="font-semibold">{{ currentUnits }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-text-muted">{{ $t("Nominal per share") }}</dt>
                        <dd>{{ membership.psn?.unit_value_label }}</dd>
                    </div>
                </dl>

                <form class="space-y-4" @submit.prevent="goReview">
                    <BaseInput
                        v-model="form.desired_units"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="off"
                        :label="$t('Desired units')"
                        :error="form.errors.desired_units"
                        required
                    />
                    <BaseInput
                        v-model="form.effective_date"
                        type="date"
                        :max="maxEffectiveDate"
                        :label="$t('Effective date')"
                        :error="form.errors.effective_date"
                        required
                    />
                    <label class="block text-sm">
                        <span class="text-text-muted">{{ $t("Reason") }}</span>
                        <select
                            v-model="form.reason"
                            required
                            class="mt-1 w-full border-border bg-surface text-text-main rounded-md shadow-sm min-h-12 text-sm"
                        >
                            <option value="">{{ $t("Select reason") }}</option>
                            <option v-for="code in reasons" :key="code" :value="code">
                                {{ $t("psn_reason_" + code) }}
                            </option>
                        </select>
                        <p v-if="form.errors.reason" class="text-sm text-danger mt-1">{{ form.errors.reason }}</p>
                    </label>
                    <BaseTextarea v-model="form.note" :label="$t('Administrative note')" :error="form.errors.note" />
                    <BaseInput v-model="form.share_reference" :label="$t('Share reference')" />
                    <label
                        v-if="form.reason === 'administrative_correction' && membership.psn?.correctable_entries?.length"
                        class="block text-sm"
                    >
                        <span class="text-text-muted">{{ $t("Correction of previous entry") }}</span>
                        <select
                            v-model="form.correction_of_entry_id"
                            class="mt-1 w-full border-border bg-surface text-text-main rounded-md shadow-sm min-h-12 text-sm"
                        >
                            <option value="">{{ $t("No specific previous entry") }}</option>
                            <option
                                v-for="entry in membership.psn.correctable_entries"
                                :key="entry.id"
                                :value="entry.id"
                            >
                                #{{ entry.id }} · {{ entry.units > 0 ? "+" : "" }}{{ entry.units }}
                            </option>
                        </select>
                    </label>
                    <p v-if="delta === 0" class="text-sm text-danger">{{ $t("Desired equals current") }}</p>
                    <div class="flex flex-col gap-3">
                        <BaseButton type="submit" class="w-full min-h-12" :disabled="!canContinue">
                            {{ $t("Continue to review") }}
                        </BaseButton>
                        <BaseButton
                            type="button"
                            variant="secondary"
                            class="w-full min-h-12"
                            :href="route('admin.cooperative.memberships.show', membership.id)"
                        >
                            {{ $t("Cancel") }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseCard v-else>
                <template #header>{{ $t("Step 2 review") }}</template>
                <div
                    v-if="isReduction"
                    class="rounded-md border border-danger bg-red-50 text-red-900 p-3 mb-4"
                >
                    <p class="font-bold tracking-wide">{{ $t("SHARE REDUCTION") }}</p>
                    <p class="text-sm mt-1">{{ $t("PSN units will decrease.") }}</p>
                </div>
                <dl class="space-y-3 text-sm mb-6">
                    <div class="rounded-md border border-border p-3">
                        <dt class="text-text-muted">{{ $t("Before") }}</dt>
                        <dd class="text-lg font-semibold">{{ currentUnits }} {{ $t("shares") }}</dd>
                        <dd class="text-text-muted">{{ formatIdr(beforeValue) }}</dd>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <dt class="text-text-muted">{{ $t("Change") }}</dt>
                        <dd class="text-lg font-semibold font-mono">
                            {{ delta > 0 ? "+" : "" }}{{ delta }} {{ $t("shares") }}
                        </dd>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <dt class="text-text-muted">{{ $t("After") }}</dt>
                        <dd class="text-lg font-semibold">{{ desiredUnits }} {{ $t("shares") }}</dd>
                        <dd class="text-text-muted">{{ formatIdr(afterValue) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Reason") }}</dt>
                        <dd>{{ $t("psn_reason_" + form.reason) }}</dd>
                    </div>
                    <div v-if="form.note">
                        <dt class="text-text-muted">{{ $t("Administrative note") }}</dt>
                        <dd class="whitespace-pre-wrap">{{ form.note }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Effective date") }}</dt>
                        <dd>{{ form.effective_date }}</dd>
                    </div>
                </dl>

                <p class="text-sm text-text-muted mb-4">
                    {{ $t("This change will be recorded as new history and will not delete previous share history.") }}
                </p>

                <label class="flex items-start gap-3 text-sm mb-4 min-h-12">
                    <input v-model="form.confirm" type="checkbox" class="mt-1 h-5 w-5" />
                    <span>{{ $t("I confirm this PSN share change") }}</span>
                </label>
                <label v-if="isReduction" class="flex items-start gap-3 text-sm mb-6 min-h-12">
                    <input v-model="form.acknowledge_reduction" type="checkbox" class="mt-1 h-5 w-5" />
                    <span>{{ $t("I understand PSN units will decrease") }}</span>
                </label>

                <div class="flex flex-col gap-3">
                    <BaseButton
                        type="button"
                        class="w-full min-h-12"
                        :disabled="!form.confirm || form.processing || (isReduction && !form.acknowledge_reduction)"
                        @click="submit"
                    >
                        {{ form.processing ? $t("Recording share change") : $t("CONFIRM SHARE CHANGE") }}
                    </BaseButton>
                    <BaseButton type="button" variant="secondary" class="w-full min-h-12" :disabled="form.processing" @click="goEdit">
                        {{ $t("Back") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <Link
                :href="route('admin.cooperative.memberships.show', membership.id)"
                class="text-sm text-primary hover:underline block text-center"
            >
                {{ $t("Back to member detail") }}
            </Link>
        </div>
    </AppLayout>
</template>
