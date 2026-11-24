<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

const props = defineProps({
    summary: { type: Object, required: true },
    participants: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout :title="$t('PSN')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">
                {{ summary.label }}
            </h2>
        </template>

        <div class="space-y-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border border-border bg-surface p-5">
                    <dt class="text-sm text-text-muted">{{ $t("PSN participants") }}</dt>
                    <dd class="mt-2 text-2xl font-semibold">{{ summary.total_participants }}</dd>
                </div>
                <div class="rounded-xl border border-border bg-surface p-5">
                    <dt class="text-sm text-text-muted">{{ $t("Total PSN shares") }}</dt>
                    <dd class="mt-2 text-2xl font-semibold">{{ summary.total_current_units }}</dd>
                </div>
                <div class="rounded-xl border border-border bg-surface p-5">
                    <dt class="text-sm text-text-muted">{{ $t("Nominal per share") }}</dt>
                    <dd class="mt-2 text-2xl font-semibold break-all">{{ summary.current_unit_value_label }}</dd>
                </div>
                <div class="rounded-xl border border-border bg-surface p-5">
                    <dt class="text-sm text-text-muted">{{ $t("PSN participation value") }}</dt>
                    <dd class="mt-2 text-2xl font-semibold break-all">{{ summary.total_current_value_label }}</dd>
                </div>
            </dl>

            <BaseCard>
                <template #header>{{ $t("PSN participants") }}</template>
                <p class="text-sm text-text-muted mb-3">{{ $t("PSN admin totals help") }}</p>
                <p v-if="!participants.length" class="text-sm text-text-muted">
                    {{ $t("No PSN participants yet") }}
                </p>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="item in participants"
                        :key="item.id"
                        class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                    >
                        <div>
                            <p class="font-medium text-text-main">{{ item.member?.legal_name }}</p>
                            <p class="text-xs font-mono text-text-muted">{{ item.member?.membership_number }}</p>
                            <p class="text-sm text-text-muted mt-1">
                                {{ item.units }} {{ $t("PSN shares") }}
                                <span v-if="item.share_reference" class="font-mono"> · {{ item.share_reference }}</span>
                            </p>
                        </div>
                        <BaseButton
                            :href="route('admin.cooperative.psn.show', item.id)"
                            variant="secondary"
                            size="sm"
                        >
                            {{ $t("Open") }}
                        </BaseButton>
                    </li>
                </ul>
            </BaseCard>

            <Link
                :href="route('admin.cooperative.memberships.index')"
                class="text-sm text-primary hover:underline"
            >
                {{ $t("Back to Cooperative members") }}
            </Link>
        </div>
    </AppLayout>
</template>
