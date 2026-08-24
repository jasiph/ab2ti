<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseFileInput from "@/Components/Base/BaseFileInput.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    listing: { type: Object, default: null },
    categoryOptions: { type: Array, default: () => [] },
    priceModeOptions: { type: Array, default: () => [] },
    availabilityOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.listing?.id);
const needsAmount = computed(() => form.price_mode === "fixed" || form.price_mode === "from");

const form = useForm({
    category_id: props.listing?.category_id || "",
    title: props.listing?.title || "",
    summary: props.listing?.summary || "",
    description: props.listing?.description || "",
    price_mode: props.listing?.price_mode || "fixed",
    price_amount: props.listing?.price_amount ?? "",
    price_unit: props.listing?.price_unit || "",
    availability: props.listing?.availability || "available",
    remove_media_ids: [],
    new_image: null,
});

const attachedImages = ref([...(props.listing?.images || [])]);

const onImage = (file) => {
    form.new_image = file;
};

const dropAttached = (id) => {
    form.remove_media_ids.push(id);
    attachedImages.value = attachedImages.value.filter((item) => item.id !== id);
};

const submit = () => {
    if (isEdit.value) {
        form.post(route("account.lapak.listings.update", props.listing.id), {
            forceFormData: true,
            headers: { "X-HTTP-Method-Override": "PUT" },
        });
        return;
    }

    form.post(route("account.lapak.listings.store"), { forceFormData: true });
};
</script>

<template>
    <FrontendLayout :title="isEdit ? 'Ubah produk' : 'Tambah produk'">
        <form class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6" @submit.prevent="submit">
            <div>
                <Link :href="route('account.lapak.listings.index')" class="text-sm text-primary hover:underline">← Produk</Link>
                <h1 class="mt-2 text-2xl font-bold text-text-main">
                    {{ isEdit ? "Ubah produk" : "Tambah produk" }}
                </h1>
            </div>

            <p
                v-if="listing?.moderation_status === 'published'"
                class="rounded-lg border border-border bg-surface-variant/50 p-4 text-sm text-text-muted"
            >
                Produk ini sedang tayang. Menyimpan perubahan isi akan mengirim ulang untuk ditinjau.
            </p>

            <BaseCard>
                <template #header>Data produk</template>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1" for="category_id">Kategori</label>
                        <BaseSelect id="category_id" v-model="form.category_id" :options="categoryOptions" placeholder="Pilih kategori aktif" />
                        <p v-if="form.errors.category_id" class="text-sm text-danger mt-1">{{ form.errors.category_id }}</p>
                    </div>
                    <BaseInput id="title" v-model="form.title" label="Judul" :error="form.errors.title" required maxlength="255" />
                    <BaseInput id="summary" v-model="form.summary" label="Ringkasan" :error="form.errors.summary" maxlength="500" />
                    <BaseTextarea id="description" v-model="form.description" label="Deskripsi" :error="form.errors.description" />
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>Harga dan ketersediaan</template>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1" for="price_mode">Cara menampilkan harga</label>
                        <BaseSelect id="price_mode" v-model="form.price_mode" :options="priceModeOptions" />
                    </div>
                    <BaseInput
                        v-if="needsAmount || form.price_mode === 'negotiable'"
                        id="price_amount"
                        v-model="form.price_amount"
                        type="number"
                        label="Nominal (IDR)"
                        :required="needsAmount"
                        :error="form.errors.price_amount"
                    />
                    <BaseInput id="price_unit" v-model="form.price_unit" label="Satuan (opsional, misalnya kg)" :error="form.errors.price_unit" />
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1" for="availability">Ketersediaan</label>
                        <BaseSelect id="availability" v-model="form.availability" :options="availabilityOptions" />
                    </div>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>Gambar</template>
                <p class="text-sm text-text-muted mb-3">
                    Unggah gambar milik Anda. Anda tidak dapat memilih berkas dari pustaka staf.
                </p>
                <div class="flex flex-wrap gap-3 mb-3">
                    <div v-for="image in attachedImages" :key="image.id" class="relative">
                        <img :src="image.thumbnail_url || image.url" alt="" class="h-20 w-20 rounded border border-border object-cover" />
                        <button type="button" class="absolute -top-1 -right-1 text-xs bg-danger text-white rounded px-1" @click="dropAttached(image.id)">
                            ×
                        </button>
                    </div>
                </div>
                <BaseFileInput
                    id="new_image"
                    label="Unggah gambar"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    :error="form.errors.new_image"
                    @change="onImage"
                />
            </BaseCard>

            <div class="flex justify-end gap-3">
                <Link :href="route('account.lapak.listings.index')" class="text-sm text-text-muted min-h-control inline-flex items-center">Batal</Link>
                <BaseButton type="submit" :loading="form.processing">Simpan</BaseButton>
            </div>
        </form>
    </FrontendLayout>
</template>
