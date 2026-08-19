<script setup>
import { useForm, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import RichEditor from "@/Components/RichEditor.vue";
import MediaPickerModal from "@/Components/MediaPickerModal.vue";
import imageCompression from "browser-image-compression";
import { computed, ref } from "vue";

const props = defineProps({
    page: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    title: props.page?.title || "",
    slug: props.page?.slug || "",
    content: props.page?.content || "",
    meta_description: props.page?.meta_description || "",
    is_active:
        props.page?.is_active !== undefined
            ? Boolean(props.page.is_active)
            : true,
    new_cover: null,
    existing_cover_asset_id: null,
    remove_cover: false,
    settings: props.page?.settings || { related_links: [] },
});

const isEditMode = computed(() => !!props.page);
const showPicker = ref(false);
const uploadPreviewUrl = ref(null);
const libraryPreviewUrl = ref(null);
const isCompressing = ref(false);

const coverPreview = computed(() => {
    if (uploadPreviewUrl.value) return uploadPreviewUrl.value;
    if (libraryPreviewUrl.value) return libraryPreviewUrl.value;
    if (!form.remove_cover && props.page?.media?.length > 0) {
        return props.page.media[0].original_url;
    }
    return null;
});

const handleCoverFileUpload = async (e) => {
    const rawFile = e.target.files[0];
    if (!rawFile) return;

    const allowed = /\.(jpe?g|png|gif|webp|avif|heic|heif|svg)$/i;
    if (!allowed.test(rawFile.name)) {
        alert(
            `Format file "${rawFile.name}" tidak didukung.\nFormat yang diterima: JPG, PNG, GIF, WebP, AVIF, HEIC, SVG.`,
        );
        e.target.value = "";
        return;
    }

    isCompressing.value = true;
    let fileToUpload = rawFile;

    if (rawFile.type.startsWith("image/") && !/\.(gif|svg)$/i.test(rawFile.name)) {
        try {
            const options = {
                maxSizeMB: 1,
                maxWidthOrHeight: 1920,
                useWebWorker: true,
            };
            const compressedBlob = await imageCompression(rawFile, options);
            fileToUpload = new File([compressedBlob], rawFile.name, {
                type: rawFile.type,
                lastModified: Date.now(),
            });
        } catch (error) {
            console.error("Compression error:", error);
        }
    }

    form.new_cover = fileToUpload;
    form.existing_cover_asset_id = null;
    form.remove_cover = false;
    uploadPreviewUrl.value = URL.createObjectURL(fileToUpload);
    libraryPreviewUrl.value = null;
    isCompressing.value = false;
};

const handleLibrarySelect = (img) => {
    form.existing_cover_asset_id = img.id;
    form.new_cover = null;
    form.remove_cover = false;
    libraryPreviewUrl.value = img.thumb || img.url;
    uploadPreviewUrl.value = null;
};

const removeCover = () => {
    form.new_cover = null;
    form.existing_cover_asset_id = null;
    form.remove_cover = true;
    uploadPreviewUrl.value = null;
    libraryPreviewUrl.value = null;
};

const addRelatedLink = () => {
    if (!form.settings.related_links) {
        form.settings.related_links = [];
    }
    form.settings.related_links.push({ label: "", url: "" });
};

const removeRelatedLink = (index) => {
    form.settings.related_links.splice(index, 1);
};

const submit = () => {
    form.transform((data) => {
        const cleaned = { ...data };
        if (!cleaned.new_cover) delete cleaned.new_cover;
        if (!cleaned.existing_cover_asset_id) delete cleaned.existing_cover_asset_id;
        if (!cleaned.remove_cover) delete cleaned.remove_cover;
        return cleaned;
    });

    if (isEditMode.value) {
        form.post(route("admin.pages.update", props.page.id), {
            forceFormData: true,
            headers: { "X-HTTP-Method-Override": "PUT" },
        });
    } else {
        form.post(route("admin.pages.store"), {
            forceFormData: true,
        });
    }
};

const generateSlug = (e) => {
    if (isEditMode.value) return;
    const text = e.target.value;
    form.slug = text
        .toLowerCase()
        .replace(/ /g, "-")
        .replace(/[^\w-]+/g, "");
};
</script>

<template>
    <AppLayout :title="isEditMode ? 'Edit Halaman' : 'Buat Halaman'">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ isEditMode ? "Edit Halaman" : "Buat Halaman Baru" }}
                </h2>
                <Link
                    :href="route('admin.pages.index')"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    &larr; Kembali
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8 border border-gray-200 dark:border-gray-700"
                >
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Judul Halaman
                                </label>
                                <input
                                    v-model="form.title"
                                    @input="generateSlug"
                                    type="text"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full mt-1"
                                    required
                                    placeholder="Masukan judul halaman..."
                                />
                                <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">
                                    {{ form.errors.title }}
                                </div>
                            </div>

                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Slug URL
                                </label>
                                <div class="flex items-center mt-1">
                                    <span
                                        class="text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border border-r-0 border-gray-300 dark:border-gray-600 px-3 py-2 rounded-l-md text-sm"
                                    >
                                        /
                                    </span>
                                    <input
                                        v-model="form.slug"
                                        type="text"
                                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-r-md shadow-sm w-full"
                                        required
                                    />
                                </div>
                                <div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">
                                    {{ form.errors.slug }}
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">
                                Gambar Latar Belakang (Cover)
                            </label>

                            <div
                                v-if="coverPreview"
                                class="relative mb-3 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700"
                            >
                                <img
                                    :src="coverPreview"
                                    alt="Cover Preview"
                                    class="w-full h-48 object-cover"
                                />
                                <button
                                    type="button"
                                    @click="removeCover"
                                    class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1.5 shadow-lg hover:bg-red-700 transition"
                                    title="Hapus gambar"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <label
                                    class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded border border-gray-300 dark:border-gray-600 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-600 transition shadow-sm"
                                >
                                    <svg
                                        class="w-5 h-5 text-indigo-500 dark:text-indigo-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                        />
                                    </svg>
                                    Upload Baru
                                    <span v-if="isCompressing" class="ml-2 animate-pulse text-xs text-indigo-500">(1/1 Compressing...)</span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="handleCoverFileUpload"
                                    />
                                </label>
                                <button
                                    type="button"
                                    @click="showPicker = true"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-gray-700 text-indigo-700 dark:text-indigo-300 rounded border border-indigo-200 dark:border-gray-600 text-sm font-medium hover:bg-indigo-100 dark:hover:bg-gray-600 transition shadow-sm"
                                >
                                    <svg
                                        class="w-5 h-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    Pilih dari Library
                                </button>
                            </div>
                            <div v-if="form.errors.new_cover" class="text-red-500 text-xs mt-1">
                                {{ form.errors.new_cover }}
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">
                                Konten Halaman
                            </label>

                            <div
                                class="rounded-md border border-gray-300 dark:border-gray-700 overflow-hidden dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                            >
                                <RichEditor v-model="form.content" />
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                Meta Description (SEO)
                            </label>
                            <textarea
                                v-model="form.meta_description"
                                rows="3"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full mt-1"
                                placeholder="Deskripsi singkat untuk hasil pencarian Google..."
                            ></textarea>
                            <div v-if="form.errors.meta_description" class="text-red-500 text-xs mt-1">
                                {{ form.errors.meta_description }}
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Tautan Terkait
                                </label>
                                <button
                                    type="button"
                                    @click="addRelatedLink"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-md text-xs font-medium hover:bg-green-100 dark:hover:bg-green-900/50 transition"
                                >
                                    <svg
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 4v16m8-8H4"
                                        />
                                    </svg>
                                    Tambah Tautan
                                </button>
                            </div>

                            <div
                                v-if="
                                    form.settings.related_links &&
                                    form.settings.related_links.length > 0
                                "
                                class="space-y-3"
                            >
                                <div
                                    v-for="(link, index) in form.settings.related_links"
                                    :key="index"
                                    class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600"
                                >
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <input
                                            v-model="link.label"
                                            type="text"
                                            placeholder="Label tautan..."
                                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm w-full text-sm"
                                        />
                                        <input
                                            v-model="link.url"
                                            type="text"
                                            placeholder="URL (contoh: /sejarah)"
                                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm w-full text-sm"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeRelatedLink(index)"
                                        class="flex-shrink-0 p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition"
                                    >
                                        <svg
                                            class="w-4 h-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <p
                                v-else
                                class="text-xs text-gray-400 dark:text-gray-500 italic"
                            >
                                Belum ada tautan terkait. Klik "Tambah Tautan"
                                untuk menambahkan.
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-end gap-4 border-t border-gray-100 dark:border-gray-700 pt-6 mt-6"
                        >
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:border-indigo-500 dark:focus:border-indigo-600 dark:bg-gray-900"
                                />
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                    Terbitkan Halaman
                                </span>
                            </label>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="bg-icbb-navy text-white px-6 py-3 rounded-lg font-bold shadow-md hover:bg-opacity-90 disabled:opacity-50 transition transform active:scale-95"
                            >
                                <span v-if="form.processing || isCompressing">
                                    Menyimpan...
                                </span>
                                <span v-else>
                                    {{ isEditMode ? "Update Halaman" : "Simpan Halaman" }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <MediaPickerModal
            :show="showPicker"
            @close="showPicker = false"
            @select="handleLibrarySelect"
        />
    </AppLayout>
</template>
