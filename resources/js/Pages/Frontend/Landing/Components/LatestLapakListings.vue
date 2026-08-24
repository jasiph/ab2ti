<script setup>
import PublicLapakListingCard from "@/Components/Lapak/PublicLapakListingCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";

defineProps({
    listings: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <PublicSection>
        <PublicContainer>
            <SectionHeading
                :title="$t('Latest lapak listings')"
                :description="$t('Latest lapak listings help')"
            >
                <template #action>
                    <BaseButton :href="route('lapak.index')" variant="secondary">
                        {{ $t("Explore lapak") }}
                    </BaseButton>
                </template>
            </SectionHeading>

            <div
                v-if="listings.length"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <PublicLapakListingCard
                    v-for="item in listings"
                    :key="item.url"
                    :listing="item"
                />
            </div>
            <p v-else class="text-text-muted leading-relaxed max-w-2xl">
                {{ $t("No lapak listings yet") }}
            </p>
        </PublicContainer>
    </PublicSection>
</template>
