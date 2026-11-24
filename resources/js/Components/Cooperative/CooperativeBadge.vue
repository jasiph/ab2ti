<script setup>
import { computed } from "vue";
import BaseStatusBadge from "@/Components/Base/BaseStatusBadge.vue";

const props = defineProps({
    state: {
        type: String,
        default: "none",
    },
});

const presentation = computed(() => {
    if (props.state === "active") {
        return { label: "Koperasi", tone: "success" };
    }

    if (props.state === "inactive") {
        return { label: "Koperasi nonaktif", tone: "neutral" };
    }

    return null;
});
</script>

<template>
    <BaseStatusBadge
        v-if="presentation"
        :label="$t(presentation.label)"
        :tone="presentation.tone"
    />
</template>
