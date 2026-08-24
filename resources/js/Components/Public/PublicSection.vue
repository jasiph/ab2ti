<script setup>
defineProps({
    tone: {
        type: String,
        default: "default",
    },
});
</script>

<template>
    <section
        class="py-12 md:py-16"
        :class="tone === 'muted' ? 'bg-surface-variant/40' : 'bg-background'"
    >
        <slot />
    </section>
</template>
