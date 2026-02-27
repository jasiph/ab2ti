<script setup>
import { computed } from "vue";
import BaseModal from "@/Components/Base/BaseModal.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import { router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";

const props = defineProps({
    show: Boolean,
    file: Object,
});

const emit = defineEmits(["close"]);

const close = () => emit("close");

const isImage = computed(() => props.file?.mime_type?.startsWith("image/"));

const { confirm } = useConfirm();

const deleteFile = async () => {
    const ok = await confirm({
        title: 'Delete File',
        message: 'Are you sure you want to delete this file?',
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.files.destroy", props.file.id), {
            onSuccess: () => close(),
        });
    }
};

const downloadFile = () => {
    window.open(props.file.url, "_blank");
};
</script>

<template>
    <BaseModal :show="show" @close="close" fullscreen>
        <div class="h-full flex flex-col md:flex-row">
            <!-- Left: Back Button -->
            <div class="absolute top-4 left-4 z-50">
                <button @click="close" class="bg-black/50 text-white p-2 rounded-full hover:bg-black/70">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
            </div>

            <!-- Center: Content -->
            <div class="flex-1 flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-900 overflow-auto">
                <img
                    v-if="isImage"
                    :src="props.file?.url"
                    class="max-w-full max-h-full object-contain shadow-lg"
                    alt="Full Preview"
                />
                <div v-else class="text-center">
                   <svg class="w-24 h-24 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-lg text-gray-600 dark:text-gray-300">{{ props.file?.file_name }}</p>
                    <p class="text-sm text-gray-500">{{ props.file?.mime_type }}</p>
                </div>
            </div>

            <!-- Right: Sidebar/Menu -->
            <div class="w-full md:w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 p-6 flex flex-col shadow-xl z-20">
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">File Details</h3>
                    <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        <div>
                            <span class="block font-medium text-gray-500">Name</span>
                            {{ props.file?.name }}
                        </div>
                         <div>
                            <span class="block font-medium text-gray-500">File Name</span>
                            {{ props.file?.file_name }}
                        </div>
                        <div>
                            <span class="block font-medium text-gray-500">Size</span>
                            {{ (props.file?.size / 1024).toFixed(2) }} KB
                        </div>
                        <div>
                            <span class="block font-medium text-gray-500">Type</span>
                            {{ props.file?.mime_type }}
                        </div>
                        <div>
                            <span class="block font-medium text-gray-500">Uploaded</span>
                            {{ new Date(props.file?.created_at).toLocaleDateString() }}
                        </div>
                    </div>
                </div>

                <div class="mt-auto space-y-3">
                    <BaseButton @click="downloadFile" class="w-full justify-center">
                        Download / Open
                    </BaseButton>
                    <BaseButton @click="deleteFile" variant="danger" class="w-full justify-center">
                        Delete File
                    </BaseButton>
                </div>
            </div>
        </div>
    </BaseModal>
</template>
