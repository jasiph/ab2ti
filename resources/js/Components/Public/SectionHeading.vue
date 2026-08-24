<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
        <div class="max-w-2xl">
            <h2 class="text-2xl md:text-3xl font-bold text-text-main leading-tight">
                {{ title }}
            </h2>
            <p v-if="description" class="mt-1 text-sm sm:text-base text-text-muted leading-relaxed">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
    </div>
</template>
