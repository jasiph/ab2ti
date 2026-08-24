<script setup>
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseList from "@/Components/Base/BaseList.vue";
import BasePagination from "@/Components/Base/BasePagination.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";
import LapakStatusBadge from "@/Components/Lapak/LapakStatusBadge.vue";

defineProps({
    categories: Object,
    canManage: Boolean,
});

const editingId = ref(null);

const form = useForm({
    name: "",
    slug: "",
    description: "",
    sort_order: 0,
    is_active: true,
});

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    form.sort_order = 0;
};

const startEdit = (item) => {
    editingId.value = item.id;
    form.name = item.name;
    form.slug = item.slug;
    form.description = item.description || "";
    form.sort_order = item.sort_order;
    form.is_active = item.is_active;
};

const generateSlug = () => {
    if (editingId.value && form.slug) {
        return;
    }
    form.slug = form.name.toLowerCase().replace(/ /g, "-").replace(/[^\w-]+/g, "");
};

const submit = () => {
    if (editingId.value) {
        form.put(route("admin.lapak.categories.update", editingId.value), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route("admin.lapak.categories.store"), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const toggle = (item) => {
    router.patch(route("admin.lapak.categories.toggle", item.id), {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Kategori Lapak">
        <template #header>
            <h2 class="font-semibold text-xl text-text-main leading-tight">Kategori Lapak</h2>
        </template>

        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
            <BaseCard v-if="canManage">
                <template #header>{{ editingId ? "Ubah kategori" : "Tambah kategori" }}</template>
                <form class="space-y-4" @submit.prevent="submit">
                    <BaseInput id="category-name" v-model="form.name" label="Nama" :error="form.errors.name" required @input="generateSlug" />
                    <BaseInput id="category-slug" v-model="form.slug" label="Slug" :error="form.errors.slug" required />
                    <BaseTextarea id="category-description" v-model="form.description" label="Deskripsi" :error="form.errors.description" :rows="2" />
                    <BaseInput id="category-sort" v-model="form.sort_order" type="number" label="Urutan" :error="form.errors.sort_order" />
                    <label class="flex items-center gap-2 text-sm text-text-main">
                        <input v-model="form.is_active" type="checkbox" />
                        Aktif
                    </label>
                    <div class="flex justify-end gap-3">
                        <BaseButton v-if="editingId" type="button" variant="ghost" @click="resetForm">Batal</BaseButton>
                        <BaseButton type="submit" :disabled="form.processing">Simpan</BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseList :items="categories">
                <template #header>
                    <p class="text-sm text-text-muted">
                        Kategori milik platform, dipakai bersama oleh banyak Lapak. Kategori yang dipakai produk tidak dihapus; nonaktifkan saja.
                    </p>
                </template>
                <template #title="{ item }">{{ item.name }}</template>
                <template #description="{ item }">
                    <span class="font-mono text-xs">{{ item.slug }}</span>
                    <span class="hidden sm:inline text-border">·</span>
                    <span>{{ item.listings_count }} produk</span>
                </template>
                <template #right="{ item }">
                    <LapakStatusBadge
                        :status="item.is_active ? 'available' : 'unavailable'"
                        :label="item.is_active ? 'Aktif' : 'Nonaktif'"
                    />
                    <button
                        v-if="canManage"
                        type="button"
                        class="text-sm font-medium text-primary hover:underline"
                        @click="startEdit(item)"
                    >
                        Ubah
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="text-sm font-medium text-text-muted hover:text-text-main"
                        @click="toggle(item)"
                    >
                        {{ item.is_active ? "Nonaktifkan" : "Aktifkan" }}
                    </button>
                </template>
                <template #pagination>
                    <BasePagination :links="categories.links" />
                </template>
            </BaseList>
        </div>
    </AppLayout>
</template>
