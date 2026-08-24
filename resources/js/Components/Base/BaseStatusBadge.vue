<script setup>
import { computed } from "vue";

const props = defineProps({
    label: { type: String, required: true },
    tone: {
        type: String,
        default: "neutral",
    },
});

const toneClasses = computed(() => {
    const tones = {
        success: "bg-success/10 text-success border-success/20",
        warning: "bg-warning/10 text-warning border-warning/20",
        danger: "bg-danger/10 text-danger border-danger/20",
        info: "bg-info/10 text-info border-info/20",
        neutral: "bg-surface-variant text-text-muted border-border",
    };

    return tones[props.tone] || tones.neutral;
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="toneClasses"
    >
        {{ label }}
    </span>
</template>
