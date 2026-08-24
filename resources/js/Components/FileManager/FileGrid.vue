<script setup>
import BaseCard from "@/Components/Base/BaseCard.vue";

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["file-click"]);

const isImage = (file) => {
    return file.mime_type?.startsWith("image/");
};

const onFileClick = (file) => {
    emit("file-click", file);
};
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div
            v-for="file in files"
            :key="file.id"
            class="group relative cursor-pointer"
            @click="onFileClick(file)"
        >
            <BaseCard class="h-40 flex items-center justify-center overflow-hidden hover:shadow-lg transition">
                <img
                    v-if="isImage(file)"
                    :src="file.thumbnail_url || file.url"
                    class="w-full h-full object-cover"
                    alt="preview"
                />
                <div v-else class="flex flex-col items-center p-4">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-xs mt-2 text-center break-all line-clamp-2">{{ file.name }}</span>
                </div>
            </BaseCard>
        </div>
    </div>
</template>
