<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseInput from "@/Components/Base/BaseInput.vue";
import BaseSelect from "@/Components/Base/BaseSelect.vue";
import BaseTextarea from "@/Components/Base/BaseTextarea.vue";
import RichEditor from "@/Components/RichEditor.vue";
import MediaPickerModal from "@/Components/MediaPickerModal.vue";
import PublicationStatusBadge from "@/Components/Publishing/PublicationStatusBadge.vue";
import { useConfirm } from "@/Composables/useConfirm";
import { usePermission } from "@/Composables/usePermission";
import imageCompression from "browser-image-compression";

const props = defineProps({
    publication: {
        type: Object,
        default: null,
    },
    typeOptions: {
        type: Array,
        default: () => [],
    },
});

const { confirm } = useConfirm();
const { can } = usePermission();
const isEdit = computed(() => !!props.publication?.id);

const typeSelectOptions = props.typeOptions.map((value) => ({
    value,
    label: {
        berita: "Berita",
        kegiatan: "Kegiatan",
        siaran_pers: "Siaran pers",
        liputan_media: "Liputan media",
    }[value] || value,
}));

const form = useForm({
    type: props.publication?.type || "berita",
    title: props.publication?.title || "",
    slug: props.publication?.slug || "",
    excerpt: props.publication?.excerpt || "",
    content: props.publication?.content || "",
    meta_title: props.publication?.meta_title || "",
    meta_description: props.publication?.meta_description || "",
    youtube_url: props.publication?.youtube_url || "",
    is_cooperative_update: !!props.publication?.is_cooperative_update,
    new_cover: null,
    existing_cover_asset_id: null,
    remove_cover: false,
    new_social_image: null,
    existing_social_asset_id: null,
    remove_social_image: false,
});

const showCoverPicker = ref(false);
const showSocialPicker = ref(false);
const coverPreview = ref(props.publication?.cover_url || null);
const socialPreview = ref(props.publication?.social_image_url || null);
const isCompressing = ref(false);

const canUpdate = computed(() =>
    isEdit.value ? props.publication?.can_update : can("publishing-publications.create"),
);
const canPublish = computed(() => !!props.publication?.can_publish);
const canDelete = computed(() => !!props.publication?.can_delete);

const generateSlug = () => {
    if (isEdit.value && form.slug) return;
    form.slug = form.title
        .toLowerCase()
        .replace(/ /g, "-")
        .replace(/[^\w-]+/g, "");
};

const compressImage = async (rawFile) => {
    if (!rawFile.type.startsWith("image/") || /\.(gif|svg)$/i.test(rawFile.name)) {
        return rawFile;
    }
    try {
        const blob = await imageCompression(rawFile, {
            maxSizeMB: 1,
            maxWidthOrHeight: 1920,
            useWebWorker: true,
        });
        return new File([blob], rawFile.name, {
            type: rawFile.type,
            lastModified: Date.now(),
        });
    } catch {
        return rawFile;
    }
};

const onCoverFile = async (event) => {
    const raw = event.target.files?.[0];
    if (!raw) return;
    isCompressing.value = true;
    form.new_cover = await compressImage(raw);
    form.existing_cover_asset_id = null;
    form.remove_cover = false;
    coverPreview.value = URL.createObjectURL(form.new_cover);
    isCompressing.value = false;
};

const onSocialFile = async (event) => {
    const raw = event.target.files?.[0];
    if (!raw) return;
    isCompressing.value = true;
    form.new_social_image = await compressImage(raw);
    form.existing_social_asset_id = null;
    form.remove_social_image = false;
    socialPreview.value = URL.createObjectURL(form.new_social_image);
    isCompressing.value = false;
};

const onCoverLibrary = (img) => {
    form.existing_cover_asset_id = img.id;
    form.new_cover = null;
    form.remove_cover = false;
    coverPreview.value = img.thumb || img.url;
    showCoverPicker.value = false;
};

const onSocialLibrary = (img) => {
    form.existing_social_asset_id = img.id;
    form.new_social_image = null;
    form.remove_social_image = false;
    socialPreview.value = img.thumb || img.url;
    showSocialPicker.value = false;
};

const removeCover = () => {
    form.new_cover = null;
    form.existing_cover_asset_id = null;
    form.remove_cover = true;
    coverPreview.value = null;
};

const removeSocial = () => {
    form.new_social_image = null;
    form.existing_social_asset_id = null;
    form.remove_social_image = true;
    socialPreview.value = null;
};

const submit = () => {
    form.transform((data) => {
        const cleaned = { ...data };
        if (!cleaned.new_cover) delete cleaned.new_cover;
        if (!cleaned.existing_cover_asset_id) delete cleaned.existing_cover_asset_id;
        if (!cleaned.new_social_image) delete cleaned.new_social_image;
        if (!cleaned.existing_social_asset_id) delete cleaned.existing_social_asset_id;
        return cleaned;
    });

    if (isEdit.value) {
        form.post(route("admin.publishing.publications.update", props.publication.id), {
            forceFormData: true,
            headers: { "X-HTTP-Method-Override": "PUT" },
        });
    } else {
        form.post(route("admin.publishing.publications.store"), {
            forceFormData: true,
        });
    }
};

const publish = async () => {
    const ok = await confirm({
        title: "Publish publication",
        message: "Publish publication confirmation",
        confirmText: "Publish",
        cancelText: "Cancel",
        variant: "warning",
    });
    if (!ok) return;
    router.post(route("admin.publishing.publications.publish", props.publication.id));
};

const unpublish = async () => {
    const ok = await confirm({
        title: "Unpublish publication",
        message: "Unpublish publication confirmation",
        confirmText: "Unpublish",
        cancelText: "Cancel",
        variant: "warning",
    });
    if (!ok) return;
    router.post(route("admin.publishing.publications.unpublish", props.publication.id));
};

const destroy = async () => {
    const ok = await confirm({
        title: "Delete publication",
        message: "Delete publication confirmation",
        confirmText: "Delete",
        cancelText: "Cancel",
        variant: "danger",
    });
    if (!ok) return;
    router.delete(route("admin.publishing.publications.destroy", props.publication.id));
};
</script>

<template>
    <AppLayout :title="isEdit ? $t('Edit publication') : $t('New publication')">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-text-main leading-tight">
                        {{ isEdit ? $t("Edit publication") : $t("New publication") }}
                    </h2>
                    <p v-if="isEdit" class="text-sm text-text-muted mt-1">
                        {{ publication.title }}
                    </p>
                </div>
                <PublicationStatusBadge v-if="isEdit" :status="publication.status" />
            </div>
        </template>

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard>
                <template #header>{{ $t("Core content") }}</template>
                <div class="space-y-5">
                    <BaseInput
                        id="publication-title"
                        v-model="form.title"
                        :label="$t('Title')"
                        :error="form.errors.title"
                        required
                        @input="generateSlug"
                    />
                    <div>
                        <label for="publication-type" class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Type") }} <span class="text-danger">*</span>
                        </label>
                        <BaseSelect id="publication-type" v-model="form.type" :options="typeSelectOptions" />
                        <p v-if="form.errors.type" class="text-sm text-danger mt-1" role="alert">
                            {{ form.errors.type }}
                        </p>
                    </div>
                    <BaseInput
                        id="publication-slug"
                        v-model="form.slug"
                        :label="$t('URL slug')"
                        :error="form.errors.slug"
                        required
                    />
                    <BaseTextarea
                        id="publication-excerpt"
                        v-model="form.excerpt"
                        :label="$t('Summary')"
                        :error="form.errors.excerpt"
                        :rows="3"
                    />
                    <div>
                        <label class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Body") }}
                        </label>
                        <div class="rounded-md border border-border overflow-hidden bg-surface">
                            <RichEditor v-model="form.content" />
                        </div>
                        <p v-if="form.errors.content" class="text-sm text-danger mt-1" role="alert">
                            {{ form.errors.content }}
                        </p>
                    </div>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Presentation") }}</template>
                <div class="space-y-4">
                    <p class="text-sm text-text-muted">
                        {{ $t("Primary image help") }}
                    </p>
                    <img
                        v-if="coverPreview"
                        :src="coverPreview"
                        alt=""
                        class="w-full max-h-56 object-cover rounded-lg border border-border"
                    />
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center px-4 py-2 rounded-md border border-border bg-surface text-sm cursor-pointer min-h-control">
                            {{ $t("Upload image") }}
                            <input type="file" accept="image/*" class="sr-only" @change="onCoverFile" />
                        </label>
                        <BaseButton type="button" variant="secondary" @click="showCoverPicker = true">
                            {{ $t("Choose from library") }}
                        </BaseButton>
                        <BaseButton v-if="coverPreview" type="button" variant="ghost" @click="removeCover">
                            {{ $t("Remove image") }}
                        </BaseButton>
                    </div>
                    <p v-if="isCompressing" class="text-sm text-text-muted">{{ $t("Compressing image") }}</p>
                    <BaseInput
                        id="publication-youtube-url"
                        v-model="form.youtube_url"
                        :label="$t('YouTube video')"
                        :hint="$t('YouTube video help')"
                        :error="form.errors.youtube_url"
                    />
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            id="publication-is-cooperative-update"
                            v-model="form.is_cooperative_update"
                            type="checkbox"
                            class="mt-1 rounded border-border text-primary focus:ring-primary"
                        />
                        <span>
                            <span class="block text-sm font-medium text-text-main">
                                {{ $t("Show as Cooperative update") }}
                            </span>
                            <span class="block text-sm text-text-muted">
                                {{ $t("Show as Cooperative update help") }}
                            </span>
                        </span>
                    </label>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("Publishing") }}</template>
                <p class="text-sm text-text-muted mb-4">
                    {{ $t("Publishing help") }}
                </p>
                <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                    <BaseButton type="submit" :loading="form.processing" :disabled="!canUpdate">
                        {{ isEdit ? $t("Save draft") : $t("Create draft") }}
                    </BaseButton>
                    <BaseButton
                        v-if="isEdit && publication.status === 'draft' && canPublish"
                        type="button"
                        variant="secondary"
                        @click="publish"
                    >
                        {{ $t("Publish") }}
                    </BaseButton>
                    <BaseButton
                        v-if="isEdit && publication.status === 'published' && canPublish"
                        type="button"
                        variant="secondary"
                        @click="unpublish"
                    >
                        {{ $t("Unpublish") }}
                    </BaseButton>
                    <a
                        v-if="publication?.public_url"
                        :href="publication.public_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center font-medium rounded-md px-4 py-2.5 text-sm border border-border min-h-control"
                    >
                        {{ $t("View public page") }}
                    </a>
                    <BaseButton
                        v-if="isEdit && publication.status === 'draft' && canDelete"
                        type="button"
                        variant="danger"
                        @click="destroy"
                    >
                        {{ $t("Delete") }}
                    </BaseButton>
                </div>
            </BaseCard>

            <BaseCard>
                <template #header>{{ $t("SEO and sharing") }}</template>
                <div class="space-y-5">
                    <p class="text-sm text-text-muted">
                        {{ $t("SEO help") }}
                    </p>
                    <BaseInput
                        id="publication-meta-title"
                        v-model="form.meta_title"
                        :label="$t('SEO title')"
                        :error="form.errors.meta_title"
                    />
                    <BaseTextarea
                        id="publication-meta-description"
                        v-model="form.meta_description"
                        :label="$t('SEO description')"
                        :error="form.errors.meta_description"
                        :rows="3"
                    />
                    <div>
                        <p class="block font-medium text-sm text-text-main mb-1">
                            {{ $t("Sharing image") }}
                        </p>
                        <img
                            v-if="socialPreview"
                            :src="socialPreview"
                            alt=""
                            class="w-full max-h-40 object-cover rounded-lg border border-border mb-3"
                        />
                        <div class="flex flex-wrap gap-3">
                            <label class="inline-flex items-center px-4 py-2 rounded-md border border-border bg-surface text-sm cursor-pointer min-h-control">
                                {{ $t("Upload image") }}
                                <input type="file" accept="image/*" class="sr-only" @change="onSocialFile" />
                            </label>
                            <BaseButton type="button" variant="secondary" @click="showSocialPicker = true">
                                {{ $t("Choose from library") }}
                            </BaseButton>
                            <BaseButton v-if="socialPreview" type="button" variant="ghost" @click="removeSocial">
                                {{ $t("Remove image") }}
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <Link
                :href="route('admin.publishing.publications.index')"
                class="text-sm text-primary hover:underline"
            >
                {{ $t("Back to publications") }}
            </Link>
        </form>

        <MediaPickerModal
            :show="showCoverPicker"
            @close="showCoverPicker = false"
            @select="onCoverLibrary"
        />
        <MediaPickerModal
            :show="showSocialPicker"
            @close="showSocialPicker = false"
            @select="onSocialLibrary"
        />
    </AppLayout>
</template>
