<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav
        v-if="links.length > 3"
        class="flex flex-wrap justify-center sm:justify-end items-center gap-1 mt-8"
        :aria-label="$t('Pagination Navigation')"
    >
        <template v-for="(link, key) in links" :key="key">
            <div
                v-if="link.url === null"
                class="px-3 py-2 min-h-control inline-flex items-center text-sm text-text-muted border border-border rounded opacity-50 cursor-not-allowed select-none"
                v-html="link.label"
            />

            <Link
                v-else
                :href="link.url"
                class="px-3 py-2 min-h-control inline-flex items-center text-sm border rounded transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                :class="{
                    'bg-primary text-white border-primary hover:bg-primary-dark':
                        link.active,
                    'bg-surface text-text-main border-border hover:bg-surface-variant hover:text-primary':
                        !link.active,
                }"
                :aria-current="link.active ? 'page' : undefined"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
