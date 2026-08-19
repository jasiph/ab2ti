<script setup>
import { ref } from "vue";
import { useForm, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import FilePickerModal from "@/Components/FileManager/FilePickerModal.vue";

const props = defineProps({
    welcomeSection: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    title: props.welcomeSection?.title || "",
    content: props.welcomeSection?.content || "",
    secondary_content: props.welcomeSection?.secondary_content || "",
    image_url: props.welcomeSection?.image_url || "",
    cta_label: props.welcomeSection?.cta_label || "",
    cta_url: props.welcomeSection?.cta_url || "",
    is_active: props.welcomeSection?.is_active ?? true,
});

const showFilePicker = ref(false);

const onFileSelect = (file) => {
    form.image_url = file.url || "";
};

const submit = () => {
    form.put(route("admin.welcome-section.update"));
};
</script>

<template>
    <AppLayout :title="$t('Welcome Section')">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.heroes.index')" class="text-text-muted hover:text-text-main transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ $t("Welcome Section") }}
                </h2>
            </div>
        </template>

        <div class="py-6 sm:py-12">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <BaseCard class="p-4 sm:p-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div>
                            <InputLabel for="title" :value="$t('Title')" />
                            <TextInput id="title" v-model="form.title" class="mt-1 block w-full" />
                            <InputError :message="form.errors.title" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="content" :value="$t('Content')" />
                            <textarea
                                id="content"
                                v-model="form.content"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-border bg-surface text-text-main focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="form.errors.content" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="secondary_content" :value="$t('Secondary Content')" />
                            <textarea
                                id="secondary_content"
                                v-model="form.secondary_content"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-border bg-surface text-text-main focus:border-primary focus:ring-primary"
                            />
                            <InputError :message="form.errors.secondary_content" class="mt-2" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="image_url" :value="$t('Image URL')" />
                                <BaseButton type="button" size="sm" variant="secondary" @click="showFilePicker = true">
                                    {{ $t("Select from Library") }}
                                </BaseButton>
                            </div>
                            <TextInput id="image_url" v-model="form.image_url" class="mt-1 block w-full" />
                            <InputError :message="form.errors.image_url" class="mt-2" />
                            <div v-if="form.image_url" class="mt-3">
                                <img :src="form.image_url" class="h-28 w-auto rounded border border-border object-cover" alt="Welcome preview" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="cta_label" :value="$t('CTA Label')" />
                                <TextInput id="cta_label" v-model="form.cta_label" class="mt-1 block w-full" />
                                <InputError :message="form.errors.cta_label" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="cta_url" :value="$t('CTA URL')" />
                                <TextInput id="cta_url" v-model="form.cta_url" class="mt-1 block w-full" />
                                <InputError :message="form.errors.cta_url" class="mt-2" />
                            </div>
                        </div>

                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-border text-primary focus:ring-primary"
                            />
                            <span class="text-sm text-text-main">{{ $t("Active") }}</span>
                        </label>

                        <div class="flex items-center justify-end">
                            <BaseButton type="submit" :disabled="form.processing">
                                {{ form.processing ? $t("Saving...") : $t("Save") }}
                            </BaseButton>
                        </div>
                    </form>
                </BaseCard>
            </div>
        </div>

        <FilePickerModal
            :show="showFilePicker"
            @close="showFilePicker = false"
            @select="onFileSelect"
        />
    </AppLayout>
</template>
