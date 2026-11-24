<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

const props = defineProps({
    summary: { type: Object, required: true },
    participant: { type: Object, required: true },
    history: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout :title="$t('PSN participation history')">
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ participant.member?.legal_name }}
                </h2>
                <p class="text-sm text-text-muted mt-1 font-mono">
                    {{ participant.member?.membership_number }}
                </p>
            </div>
        </template>

        <div class="space-y-6 max-w-3xl">
            <BaseCard>
                <template #header>{{ $t("PSN participation") }}</template>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ $t("PSN shares") }}</dt>
                        <dd class="mt-1 font-medium">{{ participant.units }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Share reference") }}</dt>
                        <dd class="mt-1 font-mono">{{ participant.share_reference || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("Nominal per share") }}</dt>
                        <dd class="mt-1">{{ summary.current_unit_value_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ $t("PSN participation value") }}</dt>
                        <dd class="mt-1">{{ participant.value_label }}</dd>
                    </div>
                </dl>
                <div v-if="participant.member" class="mt-4">
                    <BaseButton
                        :href="route('admin.membership.members.show', participant.member.id)"
                        variant="secondary"
                    >
                        {{ $t("Open AB2TI member") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("PSN participation history") }}</template>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="entry in history" :key="entry.id" class="py-3 space-y-1">
                        <p class="font-medium">{{ entry.entry_type }} · {{ entry.units }} {{ $t("PSN shares") }}</p>
                        <p class="text-text-muted">
                            {{ $t("Nominal per share") }}: {{ entry.unit_value_label }}
                            · {{ entry.amount_label }}
                        </p>
                        <p class="text-xs text-text-muted">
                            {{ $t("Share reference") }}: {{ entry.share_reference || "—" }}
                            · {{ entry.source_type }}
                            <span v-if="entry.import_batch_id"> · {{ $t("Import batch") }} #{{ entry.import_batch_id }}</span>
                            <span v-if="entry.legacy_source_row_id"> · {{ $t("Source row") }} #{{ entry.legacy_source_row_id }}</span>
                            <span v-if="entry.units_origin"> · {{ entry.units_origin }}</span>
                        </p>
                    </li>
                </ul>
            </BaseCard>

            <Link :href="route('admin.cooperative.psn.index')" class="text-sm text-primary hover:underline">
                {{ $t("Back to PSN") }}
            </Link>
        </div>
    </AppLayout>
</template>
