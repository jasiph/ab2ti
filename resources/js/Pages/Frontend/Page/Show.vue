<script setup>
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";

defineProps({
    page: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <FrontendLayout :title="page.title">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 z-0">
                <img
                    v-if="page.cover_url"
                    :src="page.cover_url"
                    :alt="page.cover_alt || page.title"
                    class="w-full h-full object-cover"
                />
                <div
                    class="absolute inset-0"
                    :class="page.cover_url ? 'bg-gradient-to-b from-black/50 to-black/70' : 'bg-gradient-to-br from-primary to-primary-dark'"
                />
            </div>

            <div class="relative z-10 max-w-5xl mx-auto px-4 py-20 sm:py-28 text-white">
                <h1 class="text-3xl sm:text-4xl font-bold leading-tight">{{ page.title }}</h1>
                <nav class="mt-4 text-sm text-white/80" :aria-label="$t('Breadcrumb')">
                    <Link
                        :href="route('welcome')"
                        class="hover:text-white focus:outline-none focus-visible:underline"
                    >
                        {{ $t("Home") }}
                    </Link>
                    <span class="mx-2" aria-hidden="true">/</span>
                    <span aria-current="page">{{ page.title }}</span>
                </nav>
            </div>
        </div>

        <section class="max-w-5xl mx-auto px-4 py-10">
            <article class="bg-surface rounded-xl border border-border p-6 sm:p-8 shadow-sm">
                <div class="prose dark:prose-invert max-w-none leading-relaxed" v-html="page.content || ''"></div>
            </article>

            <div v-if="page.related_links?.length" class="mt-8 bg-surface rounded-xl border border-border overflow-hidden">
                <div class="px-5 py-3 text-sm font-semibold text-text-main border-b border-border">
                    {{ $t("Related pages") }}
                </div>
                <Link
                    v-for="(link, index) in page.related_links"
                    :key="index"
                    :href="link.url"
                    class="flex items-center justify-between px-5 py-3 text-sm hover:bg-surface-variant transition border-b border-border last:border-b-0 focus:outline-none focus-visible:bg-surface-variant focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus"
                >
                    <span>{{ link.label }}</span>
                    <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>
    </FrontendLayout>
</template>
