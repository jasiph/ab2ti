<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import { Link, router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";

import { ref, watch, computed } from "vue";
import { debounce } from "@/Utils/debounce";

const props = defineProps({
    songs: Object,
    filters: Object,
    categories: Array,
});

const groupedCategories = computed(() => {
    return props.categories.reduce((acc, category) => {
        const type = category.type || 'General';
        if (!acc[type]) acc[type] = [];
        acc[type].push(category);
        return acc;
    }, {});
});

const search = ref(props.filters.search || "");
const category = ref(props.filters.category || "");

watch(
    [search, category],
    debounce(([valSearch, valCategory]) => {
        router.get(
            route("admin.songs.index"),
            { search: valSearch, category: valCategory },
            { preserveState: true, replace: true }
        );
    }, 300)
);

const { confirm } = useConfirm();

const deleteSong = async (id) => {
    const ok = await confirm({
        title: 'Delete Song',
        message: 'Are you sure you want to delete this song?',
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.songs.destroy", id));
    }
};
</script>

<template>
    <AppLayout :title="$t('Song Management')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t('Song Management') }}
                </h2>
                <Link
                    :href="route('admin.songs.create')"
                    class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition"
                >
                    {{ $t('Add New Song') }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                
                <!-- Filters -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="relative flex-1">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('Search songs...')"
                            class="w-full border-border bg-surface rounded-md pl-10 pr-4 py-2 focus:ring-primary focus:border-primary"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="w-full sm:w-64">
                         <select v-model="category" class="w-full border-border bg-surface rounded-md py-2 px-3 focus:ring-primary focus:border-primary">
                            <option value="">{{ $t('All Categories') }}</option>
                            <optgroup v-for="(group, type) in groupedCategories" :key="type" :label="type">
                                <option v-for="cat in group" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <BaseList
                    :items="songs"
                    :filters="filters"
                >
            <!-- Title Slot -->
            <template #title="{ item }">
                <span class="font-medium text-text-main">{{ item.title }}</span>
                <span v-if="item.alternative_title" class="ml-2 text-xs text-text-muted font-normal">
                    ({{ item.alternative_title }})
                </span>
            </template>

            <!-- Description Slot (Metadata) -->
            <template #description="{ item }">
                 <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs sm:text-sm">
                    <span v-if="item.key" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-variant text-text-muted">
                        {{ $t('Key') }}: {{ item.key }}
                    </span>
                    <span v-if="item.tempo" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-variant text-text-muted">
                        {{ item.tempo }} BPM
                    </span>
                    <span v-if="item.lyric_author || item.music_author" class="text-text-muted">
                        by {{ item.lyric_author || item.music_author }}
                    </span>
                 </div>
            </template>

            <!-- Right Slot (Desktop Actions) -->
            <template #right="{ item }">
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.songs.show', item.id)"
                        class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-full transition"
                        title="View"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </Link>
                    <Link
                        :href="route('admin.songs.edit', item.id)"
                        class="p-2 text-primary hover:text-primary-dark hover:bg-surface-variant rounded-full transition"
                        title="Edit"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </Link>
                    <button
                        @click="deleteSong(item.id)"
                        class="p-2 text-error hover:text-red-700 hover:bg-red-50 rounded-full transition"
                        title="Delete"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </template>

             <!-- Mobile Actions Slot -->
             <template #actions_mobile="{ item }">
                <Link
                    :href="route('admin.songs.show', item.id)"
                    class="text-sm font-medium text-green-600 hover:text-green-800"
                >
                    {{ $t('View') }}
                </Link>
                <Link
                    :href="route('admin.songs.edit', item.id)"
                    class="text-sm font-medium text-primary hover:text-primary-dark ml-3"
                >
                    {{ $t('Edit') }}
                </Link>
                <button
                    @click="deleteSong(item.id)"
                    class="text-sm font-medium text-error hover:text-red-700 ml-3"
                >
                    {{ $t('Delete') }}
                </button>
            </template>
            </BaseList>
            </div>
        </div>
    </AppLayout>
</template>
