<script setup>
import { ref, watch } from "vue";
import BaseModal from "@/Components/Base/BaseModal.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import FileGrid from "@/Components/FileManager/FileGrid.vue";
import axios from "axios";
import imageCompression from "browser-image-compression";

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(["close", "select"]);

const activeTab = ref("browse"); // 'browse' | 'upload'
const files = ref({ data: [], links: [] });
const loading = ref(false);
const errorMessage = ref(null);

// Upload state
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref(null);
const dragOver = ref(false);
const fileInput = ref(null);
const isCompressing = ref(false);

const fetchFiles = async (url = route("admin.files.json")) => {
    loading.value = true;
    errorMessage.value = null;
    try {
        const response = await axios.get(url, {
            headers: { Accept: "application/json" },
        });
        files.value = response.data;
    } catch (error) {
        console.error("Failed to fetch files:", error);
        errorMessage.value = "Failed to load files. " + (error.response?.data?.message || error.message);
    } finally {
        loading.value = false;
    }
};

const close = () => {
    emit("close");
    uploadError.value = null;
    uploadProgress.value = 0;
};

const onFileSelect = (file) => {
    emit("select", file);
    close();
};

// Upload handlers
const triggerFileInput = () => {
    fileInput.value?.click();
};

const onFileInputChange = (event) => {
    const selectedFiles = event.target.files;
    if (selectedFiles.length > 0) {
        uploadFiles(selectedFiles);
    }
    // Reset input so same file can be selected again
    event.target.value = "";
};

const onDrop = (event) => {
    dragOver.value = false;
    const droppedFiles = event.dataTransfer.files;
    if (droppedFiles.length > 0) {
        uploadFiles(droppedFiles);
    }
};

const uploadFiles = async (fileList) => {
    uploading.value = true;
    uploadError.value = null;
    uploadProgress.value = 0;

    // Compress images first
    isCompressing.value = true;
    const filesToUpload = [];
    for (let i = 0; i < fileList.length; i++) {
        const file = fileList[i];
        if (file.type.startsWith('image/') && !file.type.includes('svg')) {
            try {
                const compressed = await imageCompression(file, {
                    maxSizeMB: 1,
                    maxWidthOrHeight: 1920,
                    useWebWorker: true,
                });
                filesToUpload.push(new File([compressed], file.name, {
                    type: file.type,
                    lastModified: Date.now(),
                }));
            } catch (err) {
                console.error('Compression error:', err);
                filesToUpload.push(file);
            }
        } else {
            filesToUpload.push(file);
        }
    }
    isCompressing.value = false;

    const formData = new FormData();
    for (let i = 0; i < filesToUpload.length; i++) {
        formData.append("files[]", filesToUpload[i]);
    }

    try {
        const response = await axios.post(route("admin.files.upload-json"), formData, {
            headers: { "Content-Type": "multipart/form-data" },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round(
                    (progressEvent.loaded * 100) / progressEvent.total
                );
            },
        });

        if (response.data.success && response.data.files.length > 0) {
            const uploaded = response.data.files[0];
            emit("select", uploaded);
            close();

            files.value = { data: [], links: [] };
        }
    } catch (error) {
        console.error("Upload failed:", error);
        uploadError.value = error.response?.data?.message || "Upload failed. Please try again.";
    } finally {
        uploading.value = false;
        uploadProgress.value = 0;
    }
};

// Fetch files when modal is shown
watch(
    () => props.show,
    (newValue) => {
        if (newValue && files.value.data.length === 0) {
            fetchFiles();
        }
        if (newValue) {
            activeTab.value = "browse";
        }
    }
);

const onPageChange = (url) => {
    if (url) {
        fetchFiles(url);
    }
};
</script>

<template>
    <BaseModal :show="show" @close="close" maxWidth="4xl">
        <div class="flex flex-col h-[80vh]">
            <!-- Header with Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 py-3 flex items-center justify-between">
                    <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                        <!-- Browse Tab -->
                        <button
                            type="button"
                            @click="activeTab = 'browse'"
                            :class="[
                                activeTab === 'browse'
                                    ? 'bg-white dark:bg-gray-700 text-text-main shadow-sm'
                                    : 'text-text-muted hover:text-text-main',
                                'flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md transition'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="hidden sm:inline">{{ $t('Browse Library') }}</span>
                        </button>

                        <!-- Upload Tab -->
                        <button
                            type="button"
                            @click="activeTab = 'upload'"
                            :class="[
                                activeTab === 'upload'
                                    ? 'bg-white dark:bg-gray-700 text-text-main shadow-sm'
                                    : 'text-text-muted hover:text-text-main',
                                'flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md transition'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="hidden sm:inline">{{ $t('Upload New') }}</span>
                        </button>
                    </div>

                    <button @click="close" class="text-gray-400 hover:text-gray-500 ml-2">
                        <span class="sr-only">{{ $t('Close') }}</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900">
                <!-- Browse Tab -->
                <div v-if="activeTab === 'browse'" class="p-4 sm:p-6">
                    <div v-if="loading" class="flex justify-center items-center h-64">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
                    </div>
                    <div v-else-if="errorMessage" class="flex justify-center items-center h-64 text-red-500">
                        <div class="text-center">
                            <svg class="w-10 h-10 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ errorMessage }}
                        </div>
                    </div>
                    <div v-else-if="files.data && files.data.length === 0" class="flex flex-col justify-center items-center h-64 text-gray-500">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm">{{ $t('No files found') }}</p>
                        <button type="button" @click="activeTab = 'upload'" class="mt-2 text-sm text-primary hover:underline">
                            {{ $t('Upload New') }}
                        </button>
                    </div>
                    <div v-else>
                        <FileGrid :files="files.data" @file-click="onFileSelect" />
                        <div class="mt-6 flex justify-center" v-if="files.links && files.links.length > 3">
                            <div class="flex flex-wrap -mb-1">
                                <template v-for="(link, key) in files.links" :key="key">
                                    <div
                                        v-if="link.url === null"
                                        class="mr-1 mb-1 px-3 py-2 text-sm leading-4 text-gray-400 border rounded"
                                        v-html="link.label"
                                    />
                                    <button
                                        v-else
                                        class="mr-1 mb-1 px-3 py-2 text-sm leading-4 border rounded hover:bg-white dark:hover:bg-gray-700 focus:border-primary focus:text-primary transition"
                                        :class="{ 'bg-primary text-white border-primary': link.active }"
                                        @click="onPageChange(link.url)"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Tab -->
                <div v-if="activeTab === 'upload'" class="p-4 sm:p-6 flex items-center justify-center min-h-[300px]">
                    <div class="w-full max-w-md">
                        <!-- Drop Zone -->
                        <div
                            @drop.prevent="onDrop"
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @click="triggerFileInput"
                            :class="[
                                dragOver
                                    ? 'border-primary bg-primary/5'
                                    : 'border-gray-300 dark:border-gray-600 hover:border-primary/50',
                                'border-2 border-dashed rounded-2xl p-8 sm:p-12 text-center cursor-pointer transition-all duration-200'
                            ]"
                        >
                            <!-- Compressing state -->
                            <div v-if="isCompressing" class="space-y-4">
                                <div class="animate-pulse">
                                    <svg class="w-12 h-12 mx-auto text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-text-main">{{ $t('Compressing images...') }}</p>
                            </div>

                            <!-- Uploading state -->
                            <div v-else-if="uploading" class="space-y-4">
                                <div class="animate-pulse">
                                    <svg class="w-12 h-12 mx-auto text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-text-main">{{ $t('Uploading...') }}</p>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div
                                        class="bg-primary h-2 rounded-full transition-all duration-300"
                                        :style="{ width: uploadProgress + '%' }"
                                    ></div>
                                </div>
                                <p class="text-xs text-text-muted">{{ uploadProgress }}%</p>
                            </div>

                            <!-- Default state -->
                            <div v-else class="space-y-3">
                                <svg class="w-12 h-12 mx-auto text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-text-main">{{ $t('Drop images here') }}</p>
                                    <p class="text-xs text-text-muted mt-1">{{ $t('or click to browse') }}</p>
                                </div>
                                <p class="text-[11px] text-text-muted/60">JPG, PNG, GIF, WebP, SVG — max 20MB</p>
                            </div>
                        </div>

                        <!-- Error -->
                        <div v-if="uploadError" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <p class="text-sm text-red-600 dark:text-red-400">{{ uploadError }}</p>
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            multiple
                            class="hidden"
                            @change="onFileInputChange"
                        />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-4 sm:px-6 py-3 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 text-right">
                <BaseButton @click="close" variant="secondary" size="sm">{{ $t('Cancel') }}</BaseButton>
            </div>
        </div>
    </BaseModal>
</template>
