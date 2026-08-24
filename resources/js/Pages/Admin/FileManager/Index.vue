<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import FilePreviewModal from "@/Components/FileManager/FilePreviewModal.vue";
import FileGrid from "@/Components/FileManager/FileGrid.vue";

const props = defineProps({
    files: Object,
});

const showPreview = ref(false);
const selectedFile = ref(null);
const localFiles = ref([...(props.files?.data ?? [])]);

watch(
    () => props.files,
    (next) => {
        localFiles.value = [...(next?.data ?? [])];
    },
    { deep: true },
);

const onFileClick = (file) => {
    selectedFile.value = file;
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
    selectedFile.value = null;
};

const onFileDeleted = (fileId) => {
    localFiles.value = localFiles.value.filter((f) => f.id !== fileId);
};

const onFileRestored = (file) => {
    if (!file?.id) {
        return;
    }

    localFiles.value = [
        file,
        ...localFiles.value.filter((item) => item.id !== file.id),
    ];
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
                <FileGrid :files="localFiles" @file-click="onFileClick" />

                <div class="mt-6">
                    <BasePagination :links="files.links" />
                </div>
            </div>
        </div>

        <FilePreviewModal
            :show="showPreview"
            :file="selectedFile"
            @close="closePreview"
            @deleted="onFileDeleted"
            @restored="onFileRestored"
        />
    </AppLayout>
</template>
