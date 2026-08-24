<script setup>
import KnowledgeArticleCard from "@/Components/Knowledge/KnowledgeArticleCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";

defineProps({
    articles: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <PublicSection tone="muted">
        <PublicContainer>
            <SectionHeading
                :title="$t('Latest knowledge articles')"
                :description="$t('Latest knowledge articles help')"
            >
                <template #action>
                    <BaseButton :href="route('knowledge.index')" variant="secondary">
                        {{ $t("All knowledge articles") }}
                    </BaseButton>
                </template>
            </SectionHeading>

            <div
                v-if="articles.length"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <KnowledgeArticleCard
                    v-for="item in articles"
                    :key="item.id"
                    :article="item"
                />
            </div>
            <p v-else class="text-text-muted leading-relaxed max-w-2xl">
                {{ $t("No knowledge articles yet") }}
            </p>
        </PublicContainer>
    </PublicSection>
</template>
