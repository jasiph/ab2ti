<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import { Link, router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";

const props = defineProps({
    letters: Object,
});

const { confirm } = useConfirm();

const deleteLetter = async (id) => {
    const ok = await confirm({
        title: 'Delete Letter',
        message: 'Are you sure you want to delete this incoming letter?',
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.incoming-letters.destroy", id));
    }
};
</script>

<template>
    <AppLayout title="Incoming Letters">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Incoming Letter Archive
                </h2>
                <Link
                    :href="route('admin.incoming-letters.create')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition"
                >
                    Archive New Letter
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <BaseList :items="letters">
                    <template #title="{ item }">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ item.subject }}</span>
                    </template>
                    <template #description="{ item }">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            From: <span class="font-semibold">{{ item.sender }}</span>
                            <span v-if="item.reference_number"> (Ref: {{ item.reference_number }})</span>
                            <span class="mx-2">|</span>
                            Received: {{ new Date(item.received_at).toLocaleDateString() }}
                        </div>
                    </template>
                    <template #right="{ item }">
                        <div class="flex items-center gap-2">
                             <Link
                                :href="route('admin.incoming-letters.show', item.id)"
                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                View
                            </Link>
                            <button
                                @click="deleteLetter(item.id)"
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
