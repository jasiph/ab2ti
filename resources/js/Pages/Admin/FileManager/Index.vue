<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import FilePreviewModal from "@/Components/FileManager/FilePreviewModal.vue";
import FileGrid from "@/Components/FileManager/FileGrid.vue";

const props = defineProps({
    files: Object,
});

const search = ref("");
const showPreview = ref(false);
const selectedFile = ref(null);

const onFileClick = (file) => {
    selectedFile.value = file;
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
    selectedFile.value = null;
};

const isImage = (file) => {
    return file.mime_type?.startsWith("image/");
};
</script>

<template>
    <AppLayout title="File Manager">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    File Manager
                </h2>
                <BaseButton
                    variant="primary"
                    @click="router.visit(route('admin.files.create'))"
                >
                    Upload Files
                </BaseButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Grid View -->
                <FileGrid :files="files.data" @file-click="onFileClick" />

                <div class="mt-6">
                     <BasePagination :links="files.links" />
                </div>
            </div>
        </div>

        <FilePreviewModal
            :show="showPreview"
            :file="selectedFile"
            @close="closePreview"
        />
    </AppLayout>
</template>
