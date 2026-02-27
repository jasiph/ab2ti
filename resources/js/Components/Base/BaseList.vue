<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    items: {
        type: Object, // Object Pagination dari Laravel
        default: () => ({ data: [] }),
    },
});
</script>

<template>
    <div
        class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden"
    >
        <div
            v-if="$slots.header || $slots.actions"
            class="px-4 py-4 sm:px-6 border-b border-border flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-surface-variant/30"
        >
            <div v-if="$slots.header">
                <slot name="header" />
            </div>
            <div v-if="$slots.actions" class="w-full sm:w-auto">
                <slot name="actions" />
            </div>
        </div>

        <ul role="list" class="divide-y divide-border">
            <li
                v-for="(item, index) in items.data"
                :key="item.id || index"
                class="hover:bg-surface-variant/40 transition duration-150 ease-in-out group"
            >
                <div class="px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-4">
                        <div v-if="$slots.avatar" class="flex-shrink-0">
                            <slot name="avatar" :item="item" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between mb-0.5"
                            >
                                <p
                                    class="text-sm font-semibold text-text-main truncate group-hover:text-primary transition-colors"
                                >
                                    <slot name="title" :item="item">
                                        {{ item.name || "No Title" }}
                                    </slot>
                                </p>

                                <div class="block sm:hidden ml-2">
                                    <slot name="meta_mobile" :item="item" />
                                </div>
                            </div>

                            <div
                                class="text-sm text-text-muted flex items-center gap-2 truncate"
                            >
                                <slot name="description" :item="item" />
                            </div>
                        </div>

                        <div
                            class="hidden sm:flex flex-shrink-0 flex-col items-end gap-1 ml-4"
                        >
                            <slot name="right" :item="item" />
                        </div>
                    </div>

                    <div
                        v-if="$slots.actions_mobile"
                        class="mt-3 pt-3 border-t border-border sm:hidden flex justify-end gap-3"
                    >
                        <slot name="actions_mobile" :item="item" />
                    </div>
                </div>
            </li>

            <li
                v-if="items.data.length === 0"
                class="px-4 py-12 text-center text-text-muted"
            >
                <div class="flex flex-col items-center justify-center">
                    <svg
                        class="w-10 h-10 mb-2 opacity-50"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                        ></path>
                    </svg>
                    <p>Data tidak ditemukan.</p>
                </div>
            </li>
        </ul>

        <div
            v-if="items.links && items.data.length > 0"
            class="px-4 py-3 bg-surface border-t border-border sm:px-6"
        >
            <component
                :is="'BasePagination'"
                v-if="$options.components?.BasePagination"
                :links="items.links"
            />
            <slot name="pagination" />
        </div>
    </div>
</template>
