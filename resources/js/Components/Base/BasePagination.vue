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
    <div
        v-if="links.length > 3"
        class="flex flex-wrap justify-center sm:justify-end items-center gap-1 mt-4"
    >
        <template v-for="(link, key) in links" :key="key">
            <div
                v-if="link.url === null"
                class="px-3 py-1.5 text-sm text-text-muted border border-border rounded opacity-50 cursor-not-allowed select-none"
                v-html="link.label"
            />

            <Link
                v-else
                :href="link.url"
                class="px-3 py-1.5 text-sm border rounded transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                :class="{
                    'bg-primary text-white border-primary hover:bg-primary-dark':
                        link.active,
                    'bg-surface text-text-main border-border hover:bg-surface-variant hover:text-primary':
                        !link.active,
                }"
                v-html="link.label"
            />
        </template>
    </div>
</template>
