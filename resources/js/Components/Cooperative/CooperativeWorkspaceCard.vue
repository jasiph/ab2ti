<script setup>
import BaseCard from "@/Components/Base/BaseCard.vue";

defineProps({
    cooperative: { type: Object, default: () => ({ state: "none" }) },
});
</script>

<template>
    <BaseCard>
        <template #header>{{ $t("Cooperative membership") }}</template>
        <p class="text-sm text-text-muted">{{ $t("Cooperative workspace help") }}</p>
        <p class="mt-2 text-sm font-medium text-text-main">
            {{ cooperative?.state === "active" ? $t("Active") : $t("No active Cooperative membership") }}
        </p>

        <div v-if="cooperative?.state === 'active' && cooperative.psn?.has_psn" class="mt-4 space-y-2 text-sm">
            <h3 class="font-medium text-text-main">{{ $t("PSN participation") }}</h3>
            <p>{{ $t("PSN shares") }}: {{ cooperative.psn.units }} {{ $t("shares") }}</p>
            <p>{{ $t("Nominal per share") }}: {{ cooperative.psn.unit_value_label }}</p>
            <p>{{ $t("PSN participation value") }}: {{ cooperative.psn.value_label }}</p>
            <p v-if="cooperative.psn.share_reference">
                {{ $t("Share reference") }}: <span class="font-mono">{{ cooperative.psn.share_reference }}</span>
            </p>
            <ul v-if="cooperative.psn.history?.length" class="mt-2 space-y-1 text-text-muted">
                <li v-for="(entry, index) in cooperative.psn.history" :key="index">
                    {{ entry.entry_type }} · {{ entry.units }} · {{ entry.amount_label }}
                </li>
            </ul>
        </div>

        <div v-if="cooperative?.psn?.historical_funds?.length" class="mt-4 space-y-1 text-sm">
            <h3 class="font-medium text-text-main">{{ $t("Historical source of participation") }}</h3>
            <p class="text-text-muted">{{ $t("Historical Cooperative funds help") }}</p>
            <p v-for="(fund, index) in cooperative.psn.historical_funds" :key="index">
                {{ fund.fund_type === "principal_saving" ? $t("Historical principal source") : $t("Historical voluntary source") }}:
                {{ fund.amount_label }}
            </p>
        </div>
    </BaseCard>
</template>
