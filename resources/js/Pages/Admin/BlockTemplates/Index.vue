<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import BaseButton from "@/Components/Base/BaseButton.vue";

defineProps({ blockTemplates: Object, filters: Object });

const deleteBlock = (id) => {
    if (confirm($t("Delete this block template?"))) {
        router.delete(route("admin.block-templates.destroy", id));
    }
};

const toggleStatus = (id) => {
    router.patch(route("admin.block-templates.toggle", id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :title="$t('Block Template Management')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-white font-semibold text-xl text-gray-800 leading-tight">
                    {{ $t('Dynamic Block Templates') }}
                </h2>
                <Link
                    :href="route('admin.block-templates.create')"
                    class="bg-icbb-navy text-white px-4 py-2 rounded text-sm font-bold hover:bg-opacity-90"
                >
                    + {{ $t('Create New Block') }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zm-1 29a1 1 0 00-1 1v3a1 1 0 102 0v-3a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                {{ $t('block_templates_info') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="block in blockTemplates.data"
                            :key="block.id"
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 flex flex-col justify-between hover:shadow-2xl transition border border-gray-100 dark:border-gray-700 relative"
                        >
                            <div class="absolute top-4 right-4 flex items-center gap-2">
                                <span
                                    class="text-xs font-semibold px-2.5 py-0.5 rounded-full border"
                                    :class="block.is_active ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800' : 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600'"
                                >
                                    {{ block.is_active ? $t('Active') : $t('Inactive') }}
                                </span>

                                <button
                                    @click="toggleStatus(block.id)"
                                    :class="[
                                        block.is_active ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-600',
                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2'
                                    ]"
                                    role="switch"
                                    :aria-checked="Boolean(block.is_active)"
                                    :title="block.is_active ? $t('Currently Active') : $t('Set as Active')"
                                >
                                    <span class="sr-only">{{ $t('Set as Active') }}</span>
                                    <span
                                        aria-hidden="true"
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                            block.is_active ? 'translate-x-5' : 'translate-x-0'
                                        ]"
                                    ></span>
                                </button>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1 pr-24">
                                    {{ block.name }}
                                </h3>
                                <div class="mb-4 text-sm text-gray-500 dark:text-gray-400 max-w-[85%] truncate">
                                    {{ block.description || '-' }}
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                                <Link :href="route('admin.block-templates.edit', block.id)">
                                    <BaseButton variant="secondary" size="sm" :title="$t('Edit')">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span class="hidden sm:inline ml-1">{{ $t("Edit") }}</span>
                                    </BaseButton>
                                </Link>

                                <BaseButton
                                    variant="danger"
                                    size="sm"
                                    :title="$t('Delete')"
                                    @click="deleteBlock(block.id)"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span class="hidden sm:inline ml-1">{{ $t("Delete") }}</span>
                                </BaseButton>
                            </div>
                        </div>
                    </div>

                    <div v-if="!blockTemplates.data || blockTemplates.data.length === 0" class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $t('No block templates found') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $t('Get started by creating a new block template.') }}</p>
                        <div class="mt-6">
                            <Link
                                :href="route('admin.block-templates.create')"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                            >
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ $t('Create New Block') }}
                            </Link>
                        </div>
                    </div>

                    <div v-if="blockTemplates.links && blockTemplates.data.length > 0" class="mt-6">
                        <component
                            :is="'BasePagination'"
                            v-if="$options.components?.BasePagination"
                            :links="blockTemplates.links"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
