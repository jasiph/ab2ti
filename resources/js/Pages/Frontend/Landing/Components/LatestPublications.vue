<script setup>
import PublicationCard from "@/Components/Publishing/PublicationCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";

defineProps({
    publications: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <PublicSection>
        <PublicContainer>
            <SectionHeading
                :title="$t('Latest publications')"
                :description="$t('Latest publications help')"
            >
                <template #action>
                    <BaseButton :href="route('publications.index')" variant="secondary">
                        {{ $t("All publications") }}
                    </BaseButton>
                </template>
            </SectionHeading>

            <div
                v-if="publications.length"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <PublicationCard
                    v-for="item in publications"
                    :key="item.id"
                    :publication="item"
                />
            </div>
            <p v-else class="text-text-muted leading-relaxed max-w-2xl">
                {{ $t("No publications yet") }}
            </p>
        </PublicContainer>
    </PublicSection>
</template>
