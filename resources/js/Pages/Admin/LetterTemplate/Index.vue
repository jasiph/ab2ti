<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import { Link, router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";

const props = defineProps({
    templates: Object,
});

const { confirm } = useConfirm();

const deleteTemplate = async (id) => {
    const ok = await confirm({
        title: 'Delete Template',
        message: 'Are you sure you want to delete this template?',
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.letter-templates.destroy", id));
    }
};
</script>

<template>
    <AppLayout title="Letter Templates">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Letter Templates
                </h2>
                <Link
                    :href="route('admin.letter-templates.create')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition"
                >
                    Create Template
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <BaseList :items="templates">
                    <template #title="{ item }">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</span>
                    </template>
                    <template #description="{ item }">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.code }}</span>
                    </template>
                    <template #right="{ item }">
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('admin.letter-templates.edit', item.id)"
                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                Edit
                            </Link>
                            <button
                                @click="deleteTemplate(item.id)"
                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 ml-2"
                            >
                                Delete
                            </button>
                        </div>
                    </template>
                </BaseList>
            </div>
        </div>
    </AppLayout>
</template>
