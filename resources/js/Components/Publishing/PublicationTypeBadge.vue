<script setup>
import { computed } from "vue";
import BaseStatusBadge from "@/Components/Base/BaseStatusBadge.vue";

const props = defineProps({
    type: { type: String, required: true },
});

const presentation = computed(() => {
    const map = {
        berita: "Berita",
        kegiatan: "Kegiatan",
        siaran_pers: "Siaran pers",
        liputan_media: "Liputan media",
    };

    return map[props.type] || props.type;
});
</script>

<template>
    <BaseStatusBadge :label="presentation" tone="neutral" />
</template>
