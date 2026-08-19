<script setup>
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";

defineProps({
    pages: Object,
    filters: Object,
});

const deletePage = (page) => {
    if (!confirm(`Delete page "${page.title}"?`)) return;
    router.delete(route("admin.pages.destroy", page.id));
};

const toggleStatus = (page) => {
    router.patch(route("admin.pages.toggle", page.id), {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Pages">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    Dynamic Pages
                </h2>
                <BaseButton :href="route('admin.pages.create')" variant="primary">
                    Create Page
                </BaseButton>
            </div>
        </template>

        <div class="py-6 sm:py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <BaseCard class="overflow-hidden">
                    <div v-if="!pages.data?.length" class="p-8 text-center text-text-muted">
                        No pages yet.
                    </div>

                    <div v-else class="divide-y divide-border">
                        <div
                            v-for="page in pages.data"
                            :key="page.id"
                            class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-text-main truncate">
                                        {{ page.title }}
                                    </h3>
                                    <span
                                        class="text-[11px] px-2 py-0.5 rounded-full"
                                        :class="page.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ page.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </div>
                                <p class="text-sm text-text-muted mt-1">/{{ page.slug }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <a
                                    :href="`/${page.slug}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <BaseButton variant="secondary" size="sm">View</BaseButton>
                                </a>
                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    @click="toggleStatus(page)"
                                >
                                    {{ page.is_active ? "Disable" : "Enable" }}
                                </BaseButton>
                                <Link :href="route('admin.pages.edit', page.id)">
                                    <BaseButton variant="secondary" size="sm">Edit</BaseButton>
                                </Link>
                                <BaseButton
                                    variant="danger"
                                    size="sm"
                                    @click="deletePage(page)"
                                >
                                    Delete
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <div class="mt-6" v-if="pages.links?.length">
                    <BasePagination :links="pages.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
