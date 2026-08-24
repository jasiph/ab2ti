<script setup>
import { computed } from "vue";
import BaseStatusBadge from "@/Components/Base/BaseStatusBadge.vue";

const props = defineProps({
    status: { type: String, required: true },
});

const presentation = computed(() => {
    const map = {
        submitted: { label: "Diterima", tone: "info" },
        under_review: { label: "Sedang ditinjau", tone: "warning" },
        revision_requested: { label: "Revisi diminta", tone: "warning" },
        approved: { label: "Disetujui", tone: "success" },
        rejected: { label: "Ditolak", tone: "danger" },
        active: { label: "Aktif", tone: "success" },
    };

    return map[props.status] || { label: props.status, tone: "neutral" };
});
</script>

<template>
    <BaseStatusBadge :label="presentation.label" :tone="presentation.tone" />
</template>
