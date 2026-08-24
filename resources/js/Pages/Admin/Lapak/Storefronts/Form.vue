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
    lapak: { type: Object, default: null },
    memberOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.lapak?.id);
const showPicker = ref(false);

const form = useForm({
    member_id: props.lapak?.member_id || "",
    name: props.lapak?.name || "",
    slug: props.lapak?.slug || "",
    summary: props.lapak?.summary || "",
    description: props.lapak?.description || "",
    location_text: props.lapak?.location_text || "",
    contact_phone: props.lapak?.contact_phone || "",
    contact_whatsapp: props.lapak?.contact_whatsapp || "",
    contact_email: props.lapak?.contact_email || "",
    existing_cover_asset_id: null,
    remove_cover: false,
    new_cover: null,
});

const coverPreview = ref(props.lapak?.cover_url || null);

const generateSlug = () => {
    if (isEdit.value && form.slug) {
        return;
    }
    form.slug = form.name.toLowerCase().replace(/ /g, "-").replace(/[^\w-]+/g, "");
};

const onLibrarySelect = (img) => {
    form.existing_cover_asset_id = img.id;
    form.new_cover = null;
    form.remove_cover = false;
    coverPreview.value = img.thumb || img.url;
};

const clearCover = () => {
    form.existing_cover_asset_id = null;
    form.new_cover = null;
    form.remove_cover = true;
    coverPreview.value = null;
};

const submit = () => {
    if (isEdit.value) {
        form.post(route("admin.lapak.storefronts.update", props.lapak.id), {
            forceFormData: true,
            headers: { "X-HTTP-Method-Override": "PUT" },
        });
        return;
    }
    form.post(route("admin.lapak.storefronts.store"), { forceFormData: true });
};
</script>

<template>
    <AppLayout :title="isEdit ? 'Ubah Lapak' : 'Buat Lapak'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.lapak.storefronts.index')" class="text-text-muted hover:text-text-main">←</Link>
                <h2 class="font-semibold text-xl text-text-main">{{ isEdit ? "Ubah Lapak" : "Buat Lapak" }}</h2>
            </div>
        </template>

        <form class="max-w-3xl mx-auto px-4 py-6 space-y-6" @submit.prevent="submit">
            <BaseCard>
                <template #header>Data Lapak</template>
                <div class="space-y-5">
                    <div v-if="!isEdit">
                        <label class="block text-sm font-medium text-text-main mb-1" for="member_id">Pemilik (anggota AB2TI)</label>
                        <BaseSelect
                            id="member_id"
                            v-model="form.member_id"
                            :options="memberOptions"
                            placeholder="Pilih anggota yang belum punya Lapak"
                        />
                        <p v-if="!memberOptions.length" class="text-sm text-text-muted mt-1">
                            Semua anggota yang tampil di sini sudah memiliki Lapak, atau belum ada anggota.
                        </p>
                        <p v-if="form.errors.member_id" class="text-sm text-danger mt-1">{{ form.errors.member_id }}</p>
                    </div>
                    <p v-else class="text-sm text-text-muted">
                        Pemilik: <span class="font-medium text-text-main">{{ lapak.member_name }}</span>
                        <span class="font-mono text-xs"> ({{ lapak.membership_number }})</span>
                    </p>

                    <BaseInput id="name" v-model="form.name" label="Nama Lapak" :error="form.errors.name" required @input="generateSlug" />
                    <BaseInput id="slug" v-model="form.slug" label="Slug" :error="form.errors.slug" required />
                    <BaseInput id="summary" v-model="form.summary" label="Ringkasan" :error="form.errors.summary" />
                    <BaseTextarea id="description" v-model="form.description" label="Deskripsi" :error="form.errors.description" />
                    <BaseInput id="location_text" v-model="form.location_text" label="Lokasi" :error="form.errors.location_text" />
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <BaseInput id="contact_phone" v-model="form.contact_phone" label="Telepon" :error="form.errors.contact_phone" />
                        <BaseInput id="contact_whatsapp" v-model="form.contact_whatsapp" label="WhatsApp" :error="form.errors.contact_whatsapp" />
                        <BaseInput id="contact_email" v-model="form.contact_email" label="Email" :error="form.errors.contact_email" />
                    </div>
                    <p class="text-xs text-text-muted">Minimal satu saluran kontak diperlukan sebelum Lapak dapat ditayangkan.</p>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>Gambar sampul</template>
                <img v-if="coverPreview" :src="coverPreview" alt="" class="h-28 rounded border border-border object-cover mb-3" />
                <div class="flex flex-wrap gap-2">
                    <BaseButton type="button" size="sm" variant="secondary" @click="showPicker = true">
                        Pilih dari pustaka
                    </BaseButton>
                    <BaseButton v-if="coverPreview" type="button" size="sm" variant="ghost" @click="clearCover">
                        Hapus sampul
                    </BaseButton>
                </div>
            </BaseCard>

            <div class="flex justify-end gap-3">
                <Link :href="route('admin.lapak.storefronts.index')" class="text-sm text-text-muted">Batal</Link>
                <BaseButton type="submit" :disabled="form.processing">Simpan</BaseButton>
            </div>
        </form>

        <MediaPickerModal :show="showPicker" @close="showPicker = false" @select="onLibrarySelect" />
    </AppLayout>
</template>
