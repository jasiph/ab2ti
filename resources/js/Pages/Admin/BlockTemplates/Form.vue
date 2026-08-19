<script setup>
import { useForm, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { computed } from "vue";

const props = defineProps({
    blockTemplate: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.blockTemplate?.name || "",
    description: props.blockTemplate?.description || "",
    html_content: props.blockTemplate?.html_content || "",
    is_active: props.blockTemplate?.is_active !== undefined ? Boolean(props.blockTemplate.is_active) : true,
});

const isEditMode = computed(() => !!props.blockTemplate);

const submit = () => {
    if (isEditMode.value) {
        form.put(route("admin.block-templates.update", props.blockTemplate.id));
    } else {
        form.post(route("admin.block-templates.store"));
    }
};
</script>

<template>
    <AppLayout :title="isEditMode ? 'Edit Block Template' : 'Buat Block Template'">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-white font-semibold text-xl text-gray-800 leading-tight">
                    {{ isEditMode ? "Edit Block Template" : "Buat Block Template Baru" }}
                </h2>
                <Link
                    :href="route('admin.block-templates.index')"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    &larr; Kembali
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8 border border-gray-200 dark:border-gray-700">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="col-span-1 md:col-span-2">
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Nama Block
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm w-full mt-1"
                                    required
                                    placeholder="Contoh: Highlight Banner Red"
                                />
                                <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="col-span-1 md:col-span-2">
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Deskripsi (Opsional)
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm w-full mt-1"
                                    placeholder="Jelaskan kapan block ini cocok digunakan oleh editor..."
                                ></textarea>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">
                                HTML / Tailwind Content
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                Tuliskan struktur HTML murni dengan class Tailwind di sini. Editor TipTap akan menyisipkan kode ini persis seperti yang Anda tulis.
                            </p>

                            <textarea
                                v-model="form.html_content"
                                rows="15"
                                required
                                class="font-mono text-sm border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-green-700 dark:text-green-400 focus:border-indigo-500 rounded-md shadow-sm w-full mt-1"
                                placeholder='<div class="bg-blue-100 p-4 rounded-xl">\n  <h3 class="font-bold">Judul</h3>\n</div>'
                            ></textarea>
                            <div v-if="form.errors.html_content" class="text-red-500 text-xs mt-1">
                                {{ form.errors.html_content }}
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 border-t border-gray-100 dark:border-gray-700 pt-6 mt-6">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:border-indigo-500 dark:bg-gray-900"
                                />
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Aktifkan Block Ini</span>
                            </label>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="bg-icbb-navy text-white px-6 py-3 rounded-lg font-bold shadow-md hover:bg-opacity-90 disabled:opacity-50 transition"
                            >
                                <span v-if="form.processing">Menyimpan...</span>
                                <span v-else>{{ isEditMode ? "Update Block" : "Simpan Block" }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
