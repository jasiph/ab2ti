<script setup>
import { computed } from "vue";
import BaseStatusBadge from "@/Components/Base/BaseStatusBadge.vue";

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: "" },
});

const presentation = computed(() => {
    const map = {
        draft: { label: "Draf", tone: "neutral" },
        pending_review: { label: "Menunggu tinjauan", tone: "warning" },
        published: { label: "Tayang", tone: "success" },
        rejected: { label: "Ditolak", tone: "danger" },
        suspended: { label: "Ditangguhkan", tone: "danger" },
        available: { label: "Tersedia", tone: "success" },
        unavailable: { label: "Tidak tersedia", tone: "warning" },
    };

    const mapped = map[props.status] || { label: props.status, tone: "neutral" };

    return {
        label: props.label || mapped.label,
        tone: mapped.tone,
    };
});
</script>

<template>
    <BaseStatusBadge :label="presentation.label" :tone="presentation.tone" />
</template>
