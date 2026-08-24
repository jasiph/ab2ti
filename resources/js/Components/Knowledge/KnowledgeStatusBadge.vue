<script setup>
import { computed } from "vue";
import BaseStatusBadge from "@/Components/Base/BaseStatusBadge.vue";

const props = defineProps({
    status: { type: String, required: true },
});

const presentation = computed(() => {
    const map = {
        draft: { label: "Draf", tone: "info" },
        published: { label: "Terbit", tone: "success" },
    };

    return map[props.status] || { label: props.status, tone: "neutral" };
});
</script>

<template>
    <BaseStatusBadge :label="presentation.label" :tone="presentation.tone" />
</template>
