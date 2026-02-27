<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";
import { ref, watch } from "vue";
import { debounce } from "@/Utils/debounce";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const props = defineProps({
    categories: Object,
    filters: Object,
});

const search = ref(props.filters.search || "");
const showModal = ref(false);
const editingCategory = ref(null);

const form = useForm({
    name: "",
    type: "general",
});

watch(
    search,
    debounce((val) => {
        router.get(
            route("admin.song-categories.index"),
            { search: val },
            { preserveState: true, replace: true }
        );
    }, 300)
);

const openCreateModal = () => {
    editingCategory.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.type = category.type;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    editingCategory.value = null;
};

const submit = () => {
    if (editingCategory.value) {
        form.put(route("admin.song-categories.update", editingCategory.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route("admin.song-categories.store"), {
            onSuccess: () => closeModal(),
        });
    }
};

const { confirm } = useConfirm();

const deleteCategory = async (id) => {
    const ok = await confirm({
        title: 'Delete Category',
        message: 'Are you sure you want to delete this category?',
        confirmText: 'Delete',
    });
    if (ok) {
        router.delete(route("admin.song-categories.destroy", id));
    }
};
</script>

<template>
    <AppLayout :title="$t('Song Categories')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t('Song Categories') }}
                </h2>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition"
                >
                    {{ $t('Add New Category') }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                 <div class="relative max-w-sm">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('Search categories...')"
                            class="w-full border-border bg-surface rounded-md pl-10 pr-4 py-2 focus:ring-primary focus:border-primary"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                </div>

                <BaseList :items="categories" :filters="filters">
                    <template #title="{ item }">
                        <span class="font-medium text-text-main">{{ item.name }}</span>
                    </template>
                    <template #description="{ item }">
                        <span class="text-xs text-text-muted">{{ item.type }}</span>
                    </template>
                     <template #right="{ item }">
                        <div class="flex items-center gap-2">
                            <button
                                @click="openEditModal(item)"
                                class="p-2 text-primary hover:text-primary-dark hover:bg-surface-variant rounded-full transition"
                                title="Edit"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button
                                @click="deleteCategory(item.id)"
                                class="p-2 text-error hover:text-red-700 hover:bg-red-50 rounded-full transition"
                                title="Delete"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                    <template #actions_mobile="{ item }">
                         <button @click="openEditModal(item)" class="text-sm font-medium text-primary hover:text-primary-dark">Edit</button>
                         <button @click="deleteCategory(item.id)" class="text-sm font-medium text-error hover:text-red-700 ml-3">Delete</button>
                    </template>
                </BaseList>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-text-main">
                    {{ editingCategory ? $t('Edit Category') : $t('Create Category') }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" :value="$t('Name')" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        ref="nameInput"
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                     <InputLabel for="type" :value="$t('Type')" />
                     <select v-model="form.type" class="mt-1 block w-full border-border bg-surface rounded-md shadow-sm">
                        <option value="general">General</option>
                        <option value="genre">Genre</option>
                        <option value="mood">Mood</option>
                        <option value="theme">Theme</option>
                     </select>
                     <InputError :message="form.errors.type" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> {{ $t('Cancel') }} </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit">
                        {{ $t('Save') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
