<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseFileInput from "@/Components/Base/BaseFileInput.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";

const props = defineProps({
    lapak: { type: Object, default: null },
});

const isEdit = computed(() => !!props.lapak?.id);

const form = useForm({
    name: props.lapak?.name || "",
    summary: props.lapak?.summary || "",
    description: props.lapak?.description || "",
    location_text: props.lapak?.location_text || "",
    contact_phone: props.lapak?.contact_phone || "",
    contact_whatsapp: props.lapak?.contact_whatsapp || "",
    contact_email: props.lapak?.contact_email || "",
    remove_cover: false,
    new_cover: null,
});

const coverPreview = ref(props.lapak?.cover_url || null);

const onCover = (file) => {
    form.new_cover = file;
    form.remove_cover = false;
    coverPreview.value = file ? URL.createObjectURL(file) : coverPreview.value;
};

const clearCover = () => {
    form.new_cover = null;
    form.remove_cover = true;
    coverPreview.value = null;
};

const submit = () => {
    if (isEdit.value) {
        form.post(route("account.lapak.update", props.lapak.id), {
            forceFormData: true,
            headers: { "X-HTTP-Method-Override": "PUT" },
        });
        return;
    }

    form.post(route("account.lapak.store"), { forceFormData: true });
};
</script>

<template>
    <FrontendLayout :title="isEdit ? 'Ubah Lapak Saya' : 'Buat Lapak Saya'">
        <form class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6" @submit.prevent="submit">
            <div>
                <Link :href="route('account.lapak.index')" class="text-sm text-primary hover:underline">← Lapak Saya</Link>
                <h1 class="mt-2 text-2xl font-bold text-text-main">
                    {{ isEdit ? "Ubah Lapak Saya" : "Buat Lapak Saya" }}
                </h1>
            </div>

            <p
                v-if="lapak?.moderation_status === 'published'"
                class="rounded-lg border border-border bg-surface-variant/50 p-4 text-sm text-text-muted"
            >
                Lapak ini sedang tayang. Jika Anda menyimpan perubahan isi, Lapak akan dikirim ulang untuk ditinjau dan tidak tayang sampai staf menyetujui.
            </p>

            <BaseCard>
                <template #header>Data etalase</template>
                <div class="space-y-5">
                    <BaseInput id="name" v-model="form.name" label="Nama Lapak" :error="form.errors.name" required maxlength="255" />
                    <BaseInput id="summary" v-model="form.summary" label="Ringkasan" :error="form.errors.summary" maxlength="500" />
                    <BaseTextarea id="description" v-model="form.description" label="Deskripsi" :error="form.errors.description" />
                    <BaseInput id="location_text" v-model="form.location_text" label="Lokasi" :error="form.errors.location_text" maxlength="255" />
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>Kontak penjual</template>
                <p class="text-sm text-text-muted mb-4">
                    Isian ini dimaksudkan menjadi kontak publik ketika Lapak disetujui dan etalase publik dibuka.
                    Data kontak di catatan anggota tidak disalin otomatis. Isi hanya saluran yang boleh dilihat pengunjung.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <BaseInput id="contact_phone" v-model="form.contact_phone" label="Telepon publik" :error="form.errors.contact_phone" />
                    <BaseInput id="contact_whatsapp" v-model="form.contact_whatsapp" label="WhatsApp publik" :error="form.errors.contact_whatsapp" />
                    <BaseInput id="contact_email" v-model="form.contact_email" type="email" label="Email publik" :error="form.errors.contact_email" />
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>Gambar sampul</template>
                <img v-if="coverPreview" :src="coverPreview" alt="" class="h-28 rounded border border-border object-cover mb-3" />
                <BaseFileInput
                    id="new_cover"
                    label="Unggah gambar sampul"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    :error="form.errors.new_cover"
                    hint="Unggah gambar milik Anda. Pustaka berkas staf tidak tersedia di sini."
                    @change="onCover"
                />
                <BaseButton v-if="coverPreview" type="button" size="sm" variant="ghost" class="mt-3" @click="clearCover">
                    Hapus sampul
                </BaseButton>
            </BaseCard>

            <div class="flex justify-end gap-3">
                <Link :href="route('account.lapak.index')" class="text-sm text-text-muted min-h-control inline-flex items-center">Batal</Link>
                <BaseButton type="submit" :loading="form.processing">Simpan</BaseButton>
            </div>
        </form>
    </FrontendLayout>
</template>
