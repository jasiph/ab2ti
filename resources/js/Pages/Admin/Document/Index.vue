<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import TextInput from "@/Components/TextInput.vue";
import { trans } from "laravel-vue-i18n";
import { debounce } from "@/Utils/debounce";

const props = defineProps({
    documents: Object,
    filters: Object,
});

const search = ref(props.filters.search || "");
const typeFilter = ref(props.filters.type || "all");

const debouncedSearch = debounce((value) => {
    router.get(
        route("admin.documents.index"),
        { search: value, type: typeFilter.value },
        { preserveState: true, replace: true }
    );
}, 300);

watch(search, (value) => {
    debouncedSearch(value);
});

watch(typeFilter, () => {
    router.get(
        route("admin.documents.index"),
        { search: search.value, type: typeFilter.value },
        { preserveState: true, replace: true }
    );
});

const types = {
    incoming_letter: "Incoming Letter",
    legal: "Legal Document",
    certificate: "Certificate",
    report: "Report",
    other: "Other",
};
</script>

<template>
    <AppLayout :title="trans('Document Archive')">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ trans("Document Archive") }}
                </h2>
                <Link
                    :href="route('admin.documents.create')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition w-full sm:w-auto text-center"
                >
                    {{ trans("Archive New Document") }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4">
                    <TextInput
                        v-model="search"
                        type="text"
                        :placeholder="trans('Search by Title, Number, or Source...')"
                        class="w-full sm:w-1/2"
                    />
                    <select
                        v-model="typeFilter"
                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full sm:w-auto"
                    >
                        <option value="all">{{ trans("All Types") }}</option>
                        <option v-for="(label, key) in types" :key="key" :value="key">
                            {{ trans(label) }}
                        </option>
                    </select>
                </div>

                <!-- List -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        {{ trans("Document Info") }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        {{ trans("Metadata") }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        {{ trans("Dates") }}
                                    </th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="doc in documents.data" :key="doc.id">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ doc.title }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ doc.reference_number || '-' }}
                                            </span>
                                            <span class="inline-flex mt-1 items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 w-fit">
                                                {{ trans(types[doc.type] || doc.type) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        <div class="flex flex-col">
                                            <span><span class="font-semibold">{{ trans('Source') }}:</span> {{ doc.source }}</span>
                                            <span v-if="doc.congregation_id" class="text-xs text-indigo-500">
                                                 {{ trans('Linked to Member') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                        <div class="flex flex-col">
                                            <span>{{ trans('Received') }}: {{ new Date(doc.received_at).toLocaleDateString() }}</span>
                                            <span v-if="doc.document_date" class="text-xs">
                                                {{ trans('Doc Date') }}: {{ new Date(doc.document_date).toLocaleDateString() }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <a
                                            v-if="doc.file_path"
                                            :href="doc.file_path"
                                            target="_blank"
                                            class="text-green-600 hover:text-green-900 dark:hover:text-green-400"
                                        >
                                            {{ trans("View") }}
                                        </a>
                                        <Link
                                            :href="route('admin.documents.edit', doc.id)"
                                            class="text-indigo-600 hover:text-indigo-900 dark:hover:text-indigo-400"
                                        >
                                            {{ trans("Edit") }}
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="documents.data.length === 0">
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        {{ trans("No documents found.") }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
