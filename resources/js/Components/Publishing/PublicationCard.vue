<script setup>
import { Link } from "@inertiajs/vue3";
import PublicationTypeBadge from "@/Components/Publishing/PublicationTypeBadge.vue";

defineProps({
    publication: {
        type: Object,
        required: true,
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
    <article class="bg-surface border border-border rounded-xl overflow-hidden shadow-sm h-full flex flex-col">
        <Link :href="publication.url" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">
            <div class="aspect-[16/9] bg-surface-variant overflow-hidden">
                <img
                    v-if="publication.cover_url"
                    :src="publication.cover_url"
                    :alt="publication.cover_alt || publication.title"
                    loading="lazy"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-primary/10" aria-hidden="true" />
            </div>
            <div class="p-4 sm:p-5 flex-1 flex flex-col gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <PublicationTypeBadge :type="publication.type" />
                    <time v-if="publication.published_at" class="text-xs text-text-muted" :datetime="publication.published_at">
                        {{ formatDate(publication.published_at) }}
                    </time>
                </div>
                <h3 class="text-lg font-semibold text-text-main leading-snug">
                    {{ publication.title }}
                </h3>
                <p v-if="publication.excerpt" class="text-sm text-text-muted leading-relaxed line-clamp-3">
                    {{ publication.excerpt }}
                </p>
            </div>
        </Link>
    </article>
</template>
