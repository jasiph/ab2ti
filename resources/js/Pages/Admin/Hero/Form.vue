<script setup>
import { ref, computed } from "vue";
import { useForm, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseCard from "@/Components/Base/BaseCard.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import FilePickerModal from "@/Components/FileManager/FilePickerModal.vue";

const props = defineProps({
    hero: {
        type: Object,
        default: () => ({
            title: "",
            primary_text: "",
            secondary_text: "",
            button_text: "",
            button_url: "",
            is_active: false,
            images: [],
        }),
    },
});

const form = useForm({
    title: props.hero.title || "",
    primary_text: props.hero.primary_text || "",
    secondary_text: props.hero.secondary_text || "",
    button_text: props.hero.button_text || "",
    button_url: props.hero.button_url || "",
    is_active: !!props.hero.is_active,
    file_ids: [],
    remove_media_ids: [],
});

const showFilePicker = ref(false);

// Local state for displaying images (both existing and pending)
const displayImages = ref([
    ...props.hero.images?.map(img => ({ ...img, is_existing: true })) || [],
]);

const openPicker = () => {
    showFilePicker.value = true;
};

const onFileSelect = (file) => {
    // Check if already added
    if (displayImages.value.some(img => img.file_id === file.id)) {
        return;
    }

    // Add to display
    displayImages.value.push({
        id: null,
        file_id: file.id,
        url: file.url,
        thumbnail_url: file.thumbnail_url || file.url,
        is_existing: false,
    });

    // Add to form
    form.file_ids.push(file.id);
};

const removeImage = (index) => {
    const image = displayImages.value[index];

    if (image.is_existing) {
        form.remove_media_ids.push(image.id);
    } else {
        const fileIndex = form.file_ids.indexOf(image.file_id);
        if (fileIndex > -1) {
            form.file_ids.splice(fileIndex, 1);
        }
    }

    displayImages.value.splice(index, 1);
};

const toggleActive = () => {
    form.is_active = !form.is_active;
};

const submit = () => {
    if (props.hero.id) {
        form.put(route("admin.heroes.update", props.hero.id));
    } else {
        form.post(route("admin.heroes.store"));
    }
};
</script>

<template>
    <AppLayout :title="hero.id ? $t('Edit Hero') : $t('Create Hero')">
        <template v-slot:header>
            <div class="flex items-center gap-3">
                <!-- Back button icon -->
                <Link :href="route('admin.heroes.index')" class="text-text-muted hover:text-text-main transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ hero.id ? $t('Edit Hero') : $t('Create Hero') }}
                </h2>
            </div>
        </template>

        <div class="py-6 sm:py-12">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <BaseCard class="p-4 sm:p-6">
                    <form @submit.prevent="submit">
                        <div class="space-y-6">
                            <!-- Title -->
                            <div>
                                <InputLabel for="title" :value="$t('Title')" />
                                <TextInput
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    autofocus
                                />
                                <InputError :message="form.errors.title" class="mt-2" />
                            </div>

                            <!-- Primary Text -->
                            <div>
                                <InputLabel for="primary_text" :value="$t('Primary Text')" />
                                <TextInput
                                    id="primary_text"
                                    v-model="form.primary_text"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :placeholder="$t('Main heading text')"
                                />
                                <InputError :message="form.errors.primary_text" class="mt-2" />
                            </div>

                            <!-- Secondary Text -->
                            <div>
                                <InputLabel for="secondary_text" :value="$t('Secondary Text')" />
                                <TextInput
                                    id="secondary_text"
                                    v-model="form.secondary_text"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :placeholder="$t('Subtitle or description')"
                                />
                                <InputError :message="form.errors.secondary_text" class="mt-2" />
                            </div>

                            <!-- Button Text + URL row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="button_text" :value="$t('Button Text')" />
                                    <TextInput
                                        id="button_text"
                                        v-model="form.button_text"
                                        type="text"
                                        class="mt-1 block w-full"
                                        :placeholder="$t('e.g. Learn More')"
                                    />
                                    <InputError :message="form.errors.button_text" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="button_url" :value="$t('Button URL')" />
                                    <TextInput
                                        id="button_url"
                                        v-model="form.button_url"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="https://"
                                    />
                                    <InputError :message="form.errors.button_url" class="mt-2" />
                                </div>
                            </div>

                            <!-- Active Status - Switch Toggle -->
                            <div class="flex items-center justify-between p-4 bg-surface-variant rounded-xl">
                                <div class="flex items-center gap-3">
                                    <!-- Status icon -->
                                    <div :class="[form.is_active ? 'text-green-500' : 'text-text-muted']">
                                        <svg v-if="form.is_active" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-text-main">{{ $t('Status') }}</span>
                                        <p class="text-xs text-text-muted">
                                            {{ form.is_active ? $t('Active') : $t('Inactive') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Switch Toggle -->
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    @click="toggleActive"
                                    :class="[
                                        form.is_active ? 'bg-primary' : 'bg-gray-300 dark:bg-gray-600',
                                        'relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            form.is_active ? 'translate-x-5' : 'translate-x-0',
                                            'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                                        ]"
                                    />
                                </button>
                                <InputError :message="form.errors.is_active" class="mt-2" />
                            </div>

                            <!-- Background Images (Slider) -->
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <InputLabel :value="$t('Background Images')" class="!mb-0" />
                                    <BaseButton type="button" @click="openPicker" size="sm" variant="secondary">
                                        <!-- Icon always visible -->
                                        <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <!-- Text hidden on mobile -->
                                        <span class="hidden sm:inline">{{ $t('Add Image') }}</span>
                                    </BaseButton>
                                </div>

                                <!-- Empty state -->
                                <div v-if="displayImages.length === 0"
                                    class="border-2 border-dashed border-border rounded-xl p-8 text-center cursor-pointer hover:border-primary/50 transition"
                                    @click="openPicker"
                                >
                                    <svg class="w-10 h-10 mx-auto text-text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-sm text-text-muted">{{ $t('Click to add background images') }}</p>
                                    <p class="text-xs text-text-muted/60 mt-1">{{ $t('Images will be shown as a slider') }}</p>
                                </div>

                                <!-- Image Grid -->
                                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                    <div v-for="(img, index) in displayImages" :key="index" class="relative group">
                                        <div class="aspect-video bg-surface-variant rounded-lg overflow-hidden border border-border">
                                            <img :src="img.thumbnail_url" :alt="$t('Slide') + ' ' + (index + 1)" class="object-cover w-full h-full" />
                                        </div>
                                        <!-- Remove button - always visible on mobile, hover on desktop -->
                                        <button
                                            type="button"
                                            @click="removeImage(index)"
                                            class="absolute top-1.5 right-1.5 bg-red-600 text-white rounded-full p-1 sm:opacity-0 sm:group-hover:opacity-100 transition shadow-md hover:bg-red-700"
                                            :aria-label="$t('Remove image')"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                        <!-- Badge -->
                                        <div class="absolute bottom-1.5 left-1.5">
                                            <span :class="[
                                                img.is_existing
                                                    ? 'bg-primary/80 text-white'
                                                    : 'bg-secondary/80 text-white',
                                                'text-[10px] font-medium px-1.5 py-0.5 rounded-full'
                                            ]">
                                                {{ img.is_existing ? $t('Saved') : $t('New') }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Add more button card -->
                                    <div
                                        @click="openPicker"
                                        class="aspect-video bg-surface-variant rounded-lg border-2 border-dashed border-border hover:border-primary/50 cursor-pointer transition flex items-center justify-center"
                                    >
                                        <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-between mt-8 pt-6 border-t border-border">
                            <Link :href="route('admin.heroes.index')" class="text-sm text-text-muted hover:text-text-main transition inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                <span class="hidden sm:inline">{{ $t('Cancel') }}</span>
                            </Link>

                            <BaseButton
                                type="submit"
                                variant="primary"
                                :disabled="form.processing"
                            >
                                <svg v-if="form.processing" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {{ hero.id ? $t('Update Hero') : $t('Create Hero') }}
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
