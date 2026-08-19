<script setup>
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";

const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
});

const coverImageUrl = computed(() => {
    if (props.page.media?.length) {
        return props.page.media[0].original_url;
    }
    return null;
});

const relatedLinks = computed(() => props.page.settings?.related_links || []);
</script>

<template>
    <FrontendLayout :title="page.meta_title || page.title">
        <Head>
            <meta name="description" :content="page.meta_description || page.title" />
        </Head>

        <div class="relative overflow-hidden">
            <div class="absolute inset-0 z-0">
                <img
                    v-if="coverImageUrl"
                    :src="coverImageUrl"
                    :alt="page.title"
                    class="w-full h-full object-cover"
                />
                <div
                    class="absolute inset-0"
                    :class="coverImageUrl ? 'bg-gradient-to-b from-black/50 to-black/70' : 'bg-gradient-to-br from-primary to-primary-dark'"
                />
            </div>

            <div class="relative z-10 max-w-5xl mx-auto px-4 py-20 sm:py-28 text-white">
                <h1 class="text-3xl sm:text-4xl font-bold">{{ page.title }}</h1>
                <div class="mt-4 text-sm text-white/80">
                    <Link href="/" class="hover:text-white">Home</Link>
                    <span class="mx-2">/</span>
                    <span>{{ page.title }}</span>
                </div>
            </div>
        </div>

        <section class="max-w-5xl mx-auto px-4 py-10">
            <article class="bg-surface rounded-xl border border-border p-6 sm:p-8 shadow-sm">
                <div class="prose dark:prose-invert max-w-none" v-html="page.content || ''"></div>
            </article>

            <div v-if="relatedLinks.length" class="mt-8 bg-surface rounded-xl border border-border overflow-hidden">
                <div class="px-5 py-3 text-sm font-semibold text-text-main border-b border-border">
                    Related Pages
                </div>
                <Link
                    v-for="(link, index) in relatedLinks"
                    :key="index"
                    :href="link.url"
                    class="flex items-center justify-between px-5 py-3 text-sm hover:bg-surface-variant transition border-b border-border last:border-b-0"
                >
                    <span>{{ link.label || link.url }}</span>
                    <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>
    </FrontendLayout>
</template>
