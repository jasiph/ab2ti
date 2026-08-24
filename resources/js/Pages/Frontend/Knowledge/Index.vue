<script setup>
import { computed } from "vue";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import KnowledgeArticleCard from "@/Components/Knowledge/KnowledgeArticleCard.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicArchiveSearch from "@/Components/Public/PublicArchiveSearch.vue";

const props = defineProps({
    articles: Object,
    filters: {
        type: Object,
        default: () => ({ q: "" }),
    },
});

const q = computed(() => props.filters?.q || "");
const hasResults = computed(() => (props.articles?.data?.length || 0) > 0);
</script>

<template>
    <FrontendLayout :title="$t('Teknologi Tani')">
        <PublicContainer class="py-10 sm:py-14">
            <p class="text-sm font-medium text-primary mb-2">{{ $t("Teknologi Tani") }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold text-text-main leading-tight">
                {{ $t("Teknologi Tani") }}
            </h1>
            <p class="mt-3 max-w-2xl text-base text-text-muted leading-relaxed">
                {{ $t("Knowledge intro") }}
            </p>

            <PublicArchiveSearch
                :action="route('knowledge.index')"
                :q="q"
                :label="$t('Search knowledge articles')"
                :submit-label="$t('Search')"
                :clear-href="route('knowledge.index')"
                :clear-label="$t('Clear search')"
            />

            <h2 class="sr-only">{{ $t("Knowledge article list") }}</h2>

            <div
                v-if="hasResults"
                class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
            >
                <KnowledgeArticleCard
                    v-for="item in articles.data"
                    :key="item.id"
                    :article="item"
                />
            </div>
            <p v-else class="mt-10 text-text-muted leading-relaxed max-w-2xl">
                {{ q ? $t("No knowledge articles match search") : $t("No knowledge articles yet") }}
            </p>

            <BasePagination v-if="articles.links" :links="articles.links" />
        </PublicContainer>
    </FrontendLayout>
</template>
