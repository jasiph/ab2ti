<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    letters: Object,
});

const statusColors = {
    draft: "bg-gray-100 text-gray-800",
    pending_review: "bg-yellow-100 text-yellow-800",
    approved: "bg-green-100 text-green-800",
    published: "bg-blue-100 text-blue-800",
    archived: "bg-red-100 text-red-800",
};
</script>

<template>
    <AppLayout title="Letters">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Issued Letters
                </h2>
                <Link
                    :href="route('admin.letters.create')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition"
                >
                    Create New Letter
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <BaseList :items="letters">
                    <template #title="{ item }">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ item.letter_number || 'DRAFT (No Number)' }}
                        </span>
                        <span :class="['ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full', statusColors[item.status] || 'bg-gray-100 dark:bg-gray-700 dark:text-gray-300']">
                            {{ item.status.toUpperCase() }}
                        </span>
                    </template>
                    <template #description="{ item }">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Template: {{ item.template.name }} |
                            Created by: {{ item.creator.name }} |
                            Date: {{ new Date(item.created_at).toLocaleDateString() }}
                        </div>
                    </template>
                    <template #right="{ item }">
                        <Link
                            :href="route('admin.letters.show', item.id)"
                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            View Details
                        </Link>
                    </template>
                </BaseList>
            </div>
        </div>
    </AppLayout>
</template>
