<script setup>
import FilePickerModal from "@/Components/FileManager/FilePickerModal.vue";

defineProps({
    show: Boolean,
});

const emit = defineEmits(["close", "select"]);

const onSelect = (file) => {
    emit("select", {
        ...file,
        thumb: file.thumbnail_url || file.url,
    });
};
</script>

<template>
    <FilePickerModal
        :show="show"
        @close="emit('close')"
        @select="onSelect"
    />
</template>
