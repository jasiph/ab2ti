<script setup>
defineProps({
    html: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div
        class="editorial-body prose dark:prose-invert max-w-none text-text-main leading-relaxed [&_img]:max-w-full [&_img]:h-auto [&_a]:break-words [&_table]:block [&_table]:w-full [&_table]:overflow-x-auto [&_figcaption]:text-sm [&_figcaption]:text-text-muted [&_figcaption]:mt-2 [&_.grid]:grid [&_.grid]:grid-cols-1 sm:[&_.grid]:grid-cols-2 [&_.grid]:gap-4"
        v-html="html || ''"
    />
</template>
