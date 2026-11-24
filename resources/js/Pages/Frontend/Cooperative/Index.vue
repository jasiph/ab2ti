<script setup>
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";
import PublicationCard from "@/Components/Publishing/PublicationCard.vue";

const props = defineProps({
    stats: { type: Object, required: true },
    updates: { type: Array, default: () => [] },
    contactUrl: { type: String, required: true },
    membershipUrl: { type: String, required: true },
});

const formatNumber = (value) => new Intl.NumberFormat("id-ID").format(value ?? 0);
const formatPercent = (value) => `${Number(value ?? 0).toLocaleString("id-ID", { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
</script>

<template>
    <FrontendLayout :title="$t('Cooperative')">
        <PublicSection>
            <PublicContainer>
                <p class="text-sm font-medium text-primary mb-3">{{ $t("Cooperative") }}</p>
                <h1 class="text-3xl sm:text-4xl font-bold leading-tight text-text-main">
                    Koperasi Pusat AB2TI
                </h1>
                <p class="mt-6 text-base sm:text-lg text-text-muted leading-relaxed max-w-3xl">
                    {{ $t("Cooperative landing intro") }}
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <BaseButton :href="membershipUrl" size="lg">
                        {{ $t("Learn about membership") }}
                    </BaseButton>
                    <BaseButton :href="contactUrl" variant="secondary" size="lg">
                        {{ $t("Contact the board") }}
                    </BaseButton>
                </div>
            </PublicContainer>
        </PublicSection>

        <PublicSection tone="muted">
            <PublicContainer>
                <SectionHeading :title="$t('Cooperative engagement')" />
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div v-if="stats.member_registry_complete" class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("AB2TI members") }}</dt>
                        <dd class="mt-2 text-3xl font-semibold text-text-main break-all">
                            {{ formatNumber(stats.total_ab2ti_members) }}
                        </dd>
                    </div>
                    <div v-else class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("AB2TI members") }}</dt>
                        <dd class="mt-2 text-lg font-semibold text-text-muted leading-snug">
                            {{ $t("AB2TI member count unavailable") }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("Cooperative members") }}</dt>
                        <dd class="mt-2 text-3xl font-semibold text-text-main break-all">
                            {{ formatNumber(stats.active_cooperative_members) }}
                        </dd>
                    </div>
                    <div v-if="stats.member_registry_complete" class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("Have joined the Cooperative") }}</dt>
                        <dd class="mt-2 text-3xl font-semibold text-text-main break-all">
                            {{ formatPercent(stats.participation_percentage) }}
                        </dd>
                    </div>
                    <div v-else class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("Have joined the Cooperative") }}</dt>
                        <dd class="mt-2 text-lg font-semibold text-text-muted leading-snug">
                            {{ $t("Cooperative participation rate unavailable") }}
                        </dd>
                    </div>
                    <div v-if="stats.psn_legacy_reconciliation_complete" class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("Total PSN shares") }}</dt>
                        <dd class="mt-2 text-3xl font-semibold text-text-main break-all">
                            {{ formatNumber(stats.total_psn_shares) }}
                        </dd>
                    </div>
                    <div v-else class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("Total PSN shares") }}</dt>
                        <dd class="mt-2 text-lg font-semibold text-text-muted leading-snug">
                            {{ $t("PSN totals unavailable") }}
                        </dd>
                    </div>
                    <div v-if="stats.psn_legacy_reconciliation_complete" class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("PSN participants") }}</dt>
                        <dd class="mt-2 text-3xl font-semibold text-text-main break-all">
                            {{ formatNumber(stats.psn_participants) }}
                        </dd>
                    </div>
                    <div v-else class="rounded-xl border border-border bg-surface p-5">
                        <dt class="text-sm text-text-muted">{{ $t("PSN participants") }}</dt>
                        <dd class="mt-2 text-lg font-semibold text-text-muted leading-snug">
                            {{ $t("PSN totals unavailable") }}
                        </dd>
                    </div>
                </dl>
            </PublicContainer>
        </PublicSection>

        <PublicSection>
            <PublicContainer>
                <SectionHeading :title="$t('About the Cooperative')" />
                <div class="max-w-3xl space-y-4 text-text-muted leading-relaxed">
                    <p>{{ $t("Cooperative about one") }}</p>
                    <p>{{ $t("Cooperative about two") }}</p>
                    <p>{{ $t("Cooperative about three") }}</p>
                </div>
            </PublicContainer>
        </PublicSection>

        <PublicSection tone="muted">
            <PublicContainer>
                <SectionHeading :title="$t('PSN rice milling')" />
                <div class="max-w-3xl space-y-4 text-text-muted leading-relaxed">
                    <p>{{ $t("Cooperative PSN intro") }}</p>
                    <p>{{ $t("Cooperative PSN share note") }}</p>
                </div>
            </PublicContainer>
        </PublicSection>

        <PublicSection>
            <PublicContainer>
                <SectionHeading
                    :title="$t('Latest Cooperative updates')"
                    :description="$t('Latest Cooperative updates help')"
                />
                <ul v-if="updates.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <li v-for="publication in updates" :key="publication.id">
                        <PublicationCard :publication="publication" />
                    </li>
                </ul>
                <p v-else class="text-text-muted">{{ $t("No Cooperative updates yet") }}</p>
            </PublicContainer>
        </PublicSection>

        <PublicSection tone="muted">
            <PublicContainer>
                <SectionHeading :title="$t('Why join')" />
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-5xl">
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <h3 class="font-semibold text-text-main">{{ $t("Currently available") }}</h3>
                        <ul class="mt-3 space-y-2 text-sm text-text-muted leading-relaxed list-disc pl-5">
                            <li>{{ $t("Cooperative benefit current one") }}</li>
                            <li>{{ $t("Cooperative benefit current two") }}</li>
                            <li>{{ $t("Cooperative benefit current three") }}</li>
                            <li>{{ $t("Cooperative benefit current four") }}</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-border bg-surface p-5">
                        <h3 class="font-semibold text-text-main">{{ $t("Planned") }}</h3>
                        <ul class="mt-3 space-y-2 text-sm text-text-muted leading-relaxed list-disc pl-5">
                            <li>{{ $t("Cooperative benefit planned two") }}</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <BaseButton :href="contactUrl">{{ $t("I am interested in joining") }}</BaseButton>
                    <BaseButton :href="membershipUrl" variant="secondary">
                        {{ $t("Learn about membership") }}
                    </BaseButton>
                </div>
            </PublicContainer>
        </PublicSection>
    </FrontendLayout>
</template>
