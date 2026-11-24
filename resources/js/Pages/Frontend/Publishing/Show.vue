<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import PublicationTypeBadge from "@/Components/Publishing/PublicationTypeBadge.vue";
import PublicationCard from "@/Components/Publishing/PublicationCard.vue";
import EditorialBody from "@/Components/Public/EditorialBody.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import SectionHeading from "@/Components/Public/SectionHeading.vue";

defineProps({
    publication: {
        type: Object,
        required: true,
    },
    related: {
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
    <FrontendLayout :title="publication.title">
        <PublicContainer width="prose" class="py-10 sm:py-14">
            <article>
                <nav class="text-sm text-text-muted" :aria-label="$t('Breadcrumb')">
                    <Link
                        :href="route('publications.index')"
                        class="hover:text-primary focus:outline-none focus-visible:underline"
                    >
                        {{ $t("Publications") }}
                    </Link>
                    <span class="mx-2" aria-hidden="true">/</span>
                    <Link
                        :href="route('publications.type', publication.type_path)"
                        class="hover:text-primary focus:outline-none focus-visible:underline"
                    >
                        {{ publication.type_label }}
                    </Link>
                </nav>

                <header class="mt-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <PublicationTypeBadge :type="publication.type" />
                        <time
                            v-if="publication.published_at"
                            class="text-sm text-text-muted"
                            :datetime="publication.published_at"
                        >
                            {{ formatDate(publication.published_at) }}
                        </time>
                    </div>
                    <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-text-main leading-tight">
                        {{ publication.title }}
                    </h1>
                    <p v-if="publication.excerpt" class="mt-4 text-lg text-text-muted leading-relaxed max-w-prose">
                        {{ publication.excerpt }}
                    </p>
                </header>

                <figure v-if="publication.cover_url" class="mt-8 rounded-xl overflow-hidden border border-border bg-surface-variant">
                    <img
                        :src="publication.cover_url"
                        :alt="publication.cover_alt || publication.title"
                        class="w-full h-auto max-h-[28rem] object-cover object-center"
                    />
                </figure>

                <div
                    v-if="publication.youtube?.embed_url"
                    class="mt-8 w-full overflow-hidden rounded-xl border border-border bg-black aspect-video"
                >
                    <iframe
                        class="h-full w-full"
                        :src="publication.youtube.embed_url"
                        :title="`${$t('YouTube video')}: ${publication.title}`"
                        loading="lazy"
                        allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                    />
                </div>

                <EditorialBody class="mt-8" :html="publication.content" />

                <p class="mt-10">
                    <Link
                        :href="route('publications.type', publication.type_path)"
                        class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
                    >
                        {{ $t("Back to publications") }}
                    </Link>
                </p>
            </article>
        </PublicContainer>

        <PublicContainer v-if="related.length" class="pb-12 sm:pb-16">
            <SectionHeading
                :title="$t('Related publications')"
                :description="$t('Related publications help')"
            />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <PublicationCard
                    v-for="item in related"
                    :key="item.id"
                    :publication="item"
                />
            </div>
        </PublicContainer>
    </FrontendLayout>
</template>
