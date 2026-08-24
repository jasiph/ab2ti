<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";
import MediaPickerModal from "@/Components/MediaPickerModal.vue";

const props = defineProps({
    listing: { type: Object, default: null },
    prefillLapakId: { type: [Number, String], default: null },
    lapakOptions: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    priceModeOptions: { type: Array, default: () => [] },
    availabilityOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.listing?.id);
const showPicker = ref(false);

const form = useForm({
    lapak_id: props.listing?.lapak_id || props.prefillLapakId || "",
    category_id: props.listing?.category_id || "",
    title: props.listing?.title || "",
    slug: props.listing?.slug || "",
    summary: props.listing?.summary || "",
    description: props.listing?.description || "",
    price_mode: props.listing?.price_mode || "fixed",
    price_amount: props.listing?.price_amount ?? "",
    price_unit: props.listing?.price_unit || "",
    availability: props.listing?.availability || "available",
    existing_image_asset_ids: [],
    remove_media_ids: [],
    new_image: null,
});

const attachedImages = ref([...(props.listing?.images || [])]);
const pendingLibrary = ref([]);

const needsAmount = computed(() => form.price_mode === "fixed" || form.price_mode === "from");

const generateSlug = () => {
    if (isEdit.value && form.slug) {
        return;
    }
    form.slug = form.title.toLowerCase().replace(/ /g, "-").replace(/[^\w-]+/g, "");
};

const onLibrarySelect = (img) => {
    form.existing_image_asset_ids.push(img.id);
    pendingLibrary.value.push({
        id: img.id,
        url: img.thumb || img.url,
    });
};

const dropPending = (id) => {
    form.existing_image_asset_ids = form.existing_image_asset_ids.filter((item) => item !== id);
    pendingLibrary.value = pendingLibrary.value.filter((item) => item.id !== id);
};

const dropAttached = (id) => {
    form.remove_media_ids.push(id);
    attachedImages.value = attachedImages.value.filter((item) => item.id !== id);
};

const submit = () => {
    if (!needsAmount.value) {
        form.price_amount = form.price_mode === "hidden" ? "" : form.price_amount;
    }

    if (isEdit.value) {
        form.post(route("admin.lapak.listings.update", props.listing.id), {
            forceFormData: true,
            headers: { "X-HTTP-Method-Override": "PUT" },
        });
        return;
    }

    form.post(route("admin.lapak.listings.store"), { forceFormData: true });
};
</script>

<template>
    <AppLayout :title="isEdit ? 'Ubah produk' : 'Tambah produk'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.lapak.listings.index')" class="text-text-muted hover:text-text-main">←</Link>
                <h2 class="font-semibold text-xl text-text-main">{{ isEdit ? "Ubah produk" : "Tambah produk" }}</h2>
            </div>
        </template>

        <form class="max-w-3xl mx-auto px-4 py-6 space-y-6" @submit.prevent="submit">
            <BaseCard>
                <template #header>Data produk</template>
                <div class="space-y-5">
                    <div v-if="!isEdit">
                        <label class="block text-sm font-medium text-text-main mb-1" for="lapak_id">Lapak</label>
                        <BaseSelect id="lapak_id" v-model="form.lapak_id" :options="lapakOptions" placeholder="Pilih Lapak pemilik" />
                        <p v-if="form.errors.lapak_id" class="text-sm text-danger mt-1">{{ form.errors.lapak_id }}</p>
                    </div>
                    <p v-else class="text-sm text-text-muted">
                        Lapak:
                        <span class="font-medium text-text-main">{{ listing.lapak_name }}</span>
                        · Pemilik {{ listing.member_name }}
                    </p>

                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1" for="category_id">Kategori</label>
                        <BaseSelect id="category_id" v-model="form.category_id" :options="categoryOptions" />
                        <p v-if="form.errors.category_id" class="text-sm text-danger mt-1">{{ form.errors.category_id }}</p>
                    </div>

                    <BaseInput id="title" v-model="form.title" label="Judul" :error="form.errors.title" required @input="generateSlug" />
                    <BaseInput id="slug" v-model="form.slug" label="Slug" :error="form.errors.slug" required />
                    <BaseInput id="summary" v-model="form.summary" label="Ringkasan" :error="form.errors.summary" />
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
                <div class="flex flex-wrap gap-3 mb-3">
                    <div v-for="image in attachedImages" :key="'a-' + image.id" class="relative">
                        <img :src="image.thumbnail_url || image.url" alt="" class="h-20 w-20 rounded border border-border object-cover" />
                        <button type="button" class="absolute -top-1 -right-1 text-xs bg-danger text-white rounded px-1" @click="dropAttached(image.id)">
                            ×
                        </button>
                    </div>
                    <div v-for="image in pendingLibrary" :key="'p-' + image.id" class="relative">
                        <img :src="image.url" alt="" class="h-20 w-20 rounded border border-border object-cover" />
                        <button type="button" class="absolute -top-1 -right-1 text-xs bg-danger text-white rounded px-1" @click="dropPending(image.id)">
                            ×
                        </button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 items-end">
                    <BaseButton type="button" size="sm" variant="secondary" @click="showPicker = true">
                        Pilih dari pustaka
                    </BaseButton>
                    <input type="file" accept="image/*" @change="form.new_image = $event.target.files[0]" />
                </div>
            </BaseCard>

            <div class="flex justify-end gap-3">
                <Link :href="route('admin.lapak.listings.index')" class="text-sm text-text-muted">Batal</Link>
                <BaseButton type="submit" :disabled="form.processing">Simpan</BaseButton>
            </div>
        </form>

        <MediaPickerModal :show="showPicker" @close="showPicker = false" @select="onLibrarySelect" />
    </AppLayout>
</template>
