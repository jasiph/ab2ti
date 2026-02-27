<script setup>
import { useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import imageCompression from "browser-image-compression";
import { ref } from "vue";

const form = useForm({
    files: [],
});

const isCompressing = ref(false);

const uploadFiles = () => {
    form.post(route("admin.files.store"), {
        onSuccess: () => form.reset(),
    });
};

const handleFileChange = async (event) => {
    const files = Array.from(event.target.files);
    
    isCompressing.value = true;
    
    const compressedFiles = await Promise.all(
        files.map(async (file) => {
            if (file.type.startsWith("image/")) {
                try {
                    const options = {
                        maxSizeMB: 1,
                        maxWidthOrHeight: 1920,
                        useWebWorker: true,
                    };
                    const compressedBlob = await imageCompression(file, options);
                    // Create a new file from the blob, preserving the name
                    return new File([compressedBlob], file.name, {
                        type: file.type,
                        lastModified: Date.now(),
                    });
                } catch (error) {
                    console.error("Compression error:", error);
                    return file; // Fallback to original
                }
            }
            return file;
        })
    );

    form.files = compressedFiles;
    isCompressing.value = false;
};
</script>

<template>
    <AppLayout title="Upload Files">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Upload Files
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <BaseCard class="p-6">
                    <form @submit.prevent="uploadFiles">
                        <div class="mb-6">
                            <InputLabel for="files" value="Select Files" />
                            <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-500 transition">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none">
                                            <span>Upload a file</span>
                                            <input id="file-upload" name="file-upload" type="file" class="sr-only" multiple @change="handleFileChange" accept="image/*,application/pdf" />
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        PNG, JPG, PDF up to 20MB
                                    </p>
                                </div>
                            </div>
                            
                            <div v-if="isCompressing" class="mt-4 text-center text-indigo-600">
                                <p class="animate-pulse">Compressing images...</p>
                            </div>
                            
                            <div v-if="form.files.length > 0 && !isCompressing" class="mt-4">
                                <p class="font-medium">Selected {{ form.files.length }} files:</p>
                                <ul class="list-disc list-inside text-sm text-gray-600">
                                    <li v-for="(file, index) in form.files" :key="index">{{ file.name }} ({{ (file.size / 1024).toFixed(1) }} KB)</li>
                                </ul>
                            </div>
                            <InputError :message="form.errors.files" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end">
                             <BaseButton
                                type="submit"
                                variant="primary"
                                :disabled="form.processing || isCompressing || form.files.length === 0"
                            >
                                <span v-if="form.processing">Uploading...</span>
                                <span v-else-if="isCompressing">Processing...</span>
                                <span v-else>Upload</span>
                            </BaseButton>
                        </div>
                    </form>
                </BaseCard>
            </div>
        </div>
    </AppLayout>
</template>
