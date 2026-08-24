<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import KnowledgeArticleCard from "@/Components/Knowledge/KnowledgeArticleCard.vue";
import EditorialBody from "@/Components/Public/EditorialBody.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";

defineProps({
    article: {
        type: Object,
        required: true,
    },
    furtherReading: {
        type: Array,
        default: () => [],
    },
});

const formatDate = (value) => {
    if (!value) return "";
    return new Date(value).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });
};
</script>

<template>
    <FrontendLayout :title="article.title">
        <PublicContainer width="prose" class="py-10 sm:py-14">
            <article>
                <nav class="text-sm text-text-muted" :aria-label="$t('Breadcrumb')">
                    <Link
                        :href="route('knowledge.index')"
                        class="hover:text-primary focus:outline-none focus-visible:underline"
                    >
                        {{ $t("Teknologi Tani") }}
                    </Link>
                </nav>

                <header class="mt-4">
                    <time
                        v-if="article.published_at"
                        class="text-sm text-text-muted"
                        :datetime="article.published_at"
                    >
                        {{ formatDate(article.published_at) }}
                    </time>
                    <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-text-main leading-tight">
                        {{ article.title }}
                    </h1>
                    <p v-if="article.excerpt" class="mt-4 text-lg text-text-muted leading-relaxed max-w-prose">
                        {{ article.excerpt }}
                    </p>
                </header>

                <figure v-if="article.cover_url" class="mt-8 rounded-xl overflow-hidden border border-border bg-surface-variant">
                    <img
                        :src="article.cover_url"
                        :alt="article.cover_alt || article.title"
                        class="w-full h-auto max-h-[28rem] object-cover object-center"
                    />
                </figure>

                <EditorialBody class="mt-8" :html="article.content" />

                <p class="mt-10">
                    <Link
                        :href="route('knowledge.index')"
                        class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    >
                        {{ $t("Back to knowledge articles") }}
                    </Link>
                </p>
            </article>
        </PublicContainer>

        <PublicContainer v-if="furtherReading.length" class="pb-12 sm:pb-16">
            <SectionHeading
                :title="$t('Further reading')"
                :description="$t('Further reading help')"
            />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <KnowledgeArticleCard
                    v-for="item in furtherReading"
                    :key="item.id"
                    :article="item"
                />
            </div>
        </PublicContainer>
    </FrontendLayout>
</template>
