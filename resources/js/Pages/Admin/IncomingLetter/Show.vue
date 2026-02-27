<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const props = defineProps({
    letter: Object,
    mediaUrl: String,
});
</script>

<template>
    <AppLayout title="View Incoming Letter">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Letter Details
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Sender Information</h3>
                            <dl class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex justify-between py-1 border-b dark:border-gray-700">
                                    <dt class="font-medium">Sender:</dt>
                                    <dd>{{ letter.sender }}</dd>
                                </div>
                                <div class="flex justify-between py-1 border-b dark:border-gray-700">
                                    <dt class="font-medium">Reference No:</dt>
                                    <dd>{{ letter.reference_number || '-' }}</dd>
                                </div>
                                <div class="flex justify-between py-1 border-b dark:border-gray-700">
                                    <dt class="font-medium">Received Date:</dt>
                                    <dd>{{ new Date(letter.received_at).toLocaleDateString() }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Disposition</h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap bg-gray-50 dark:bg-gray-700 p-3 rounded-md border dark:border-gray-600 min-h-[100px]">
                                {{ letter.disposition || 'No disposition notes.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-6">
                         <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Subject: {{ letter.subject }}</h3>
                    </div>

                    <div v-if="mediaUrl" class="border dark:border-gray-600 rounded-lg overflow-hidden">
                        <div class="bg-gray-100 dark:bg-gray-700 p-2 border-b dark:border-gray-600 flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Attachment Preview</span>
                            <a :href="mediaUrl" target="_blank" class="text-indigo-600 hover:underline text-sm dark:text-indigo-400">Download / Open in New Tab</a>
                        </div>
                        <div class="aspect-[4/3] w-full bg-gray-200 dark:bg-gray-900 flex items-center justify-center">
                            <iframe v-if="mediaUrl.toLowerCase().endsWith('.pdf')" :src="mediaUrl" class="w-full h-[600px]" frameborder="0"></iframe>
                            <img v-else :src="mediaUrl" class="max-h-[600px] max-w-full object-contain" />
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-gray-50 dark:bg-gray-700 border border-dashed dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400">
                        No attachment available.
                    </div>

                    <div class="mt-6 flex justify-end">
                         <Link :href="route('admin.incoming-letters.index')">
                            <SecondaryButton>Back to List</SecondaryButton>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
