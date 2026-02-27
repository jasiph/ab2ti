<script setup>
import { router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";

const props = defineProps({
    heroes: Object,
});

const toggleActive = (hero) => {
    router.put(route("admin.heroes.update", hero.id), {
        ...hero,
        is_active: !hero.is_active,
    }, {
        preserveScroll: true,
    });
};

const { confirm } = useConfirm();

const deleteHero = async (hero) => {
    const ok = await confirm({
        title: 'Delete Hero',
        message: 'Are you sure you want to delete this hero?',
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.heroes.destroy", hero.id));
    }
};
</script>

<template>
    <AppLayout title="Hero Management">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Hero Management
                </h2>
                <BaseButton
                    variant="primary"
                    @click="router.visit(route('admin.heroes.create'))"
                >
                    Create Hero
                </BaseButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <BaseList :items="heroes">
                    <template #avatar="{ item }">
                        <div class="h-12 w-12 rounded bg-gray-100 overflow-hidden">
                            <img
                                v-if="item.background_image"
                                :src="item.background_image"
                                class="h-full w-full object-cover"
                                alt="Hero"
                            />
                            <div v-else class="h-full w-full flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </template>

                    <template #title="{ item }">
                        {{ item.title }}
                    </template>

                    <template #description="{ item }">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
                            <span>Created: {{ new Date(item.created_at).toLocaleDateString() }}</span>
                            <span class="hidden sm:inline text-gray-300">•</span>
                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full cursor-pointer w-fit"
                                :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                @click.stop="toggleActive(item)"
                            >
                                {{ item.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </template>

                    <template #right="{ item }">
                        <div class="flex items-center gap-2">
                            <button
                                @click.stop="router.visit(route('admin.heroes.edit', item.id))"
                                class="p-2 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-full transition"
                                title="Edit"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button
                                @click.stop="deleteHero(item)"
                                class="p-2 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-full transition"
                                title="Delete"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </template>

                    <template #actions_mobile="{ item }">
                         <button
                            @click="router.visit(route('admin.heroes.edit', item.id))"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-900 py-1"
                        >
                            Edit
                        </button>
                        <button
                            @click="deleteHero(item)"
                            class="text-sm font-medium text-red-600 hover:text-red-900 py-1"
                        >
                            Delete
                        </button>
                    </template>
                    
                    <template #pagination>
                        <BasePagination :links="heroes.links" />
                    </template>
                </BaseList>


            </div>
        </div>
    </AppLayout>
</template>
