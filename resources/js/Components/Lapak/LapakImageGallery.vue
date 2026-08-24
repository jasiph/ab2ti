<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: "",
    },
});

const index = ref(0);
const current = computed(() => props.images[index.value] || null);
const hasMultiple = computed(() => props.images.length > 1);

const select = (next) => {
    if (!props.images.length) {
        return;
    }

    index.value = (next + props.images.length) % props.images.length;
};
</script>

<template>
    <div
        v-if="current"
        class="space-y-3"
        role="region"
        tabindex="0"
        :aria-label="$t('Listing image gallery')"
        @keydown.left.prevent="hasMultiple && select(index - 1)"
        @keydown.right.prevent="hasMultiple && select(index + 1)"
    >
        <figure class="rounded-xl overflow-hidden border border-border bg-surface-variant">
            <img
                :src="current.url"
                :alt="current.alt || title"
                class="w-full h-auto max-h-[28rem] object-contain object-center bg-surface-variant"
            />
        </figure>

        <div v-if="hasMultiple" class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="min-h-control min-w-11 px-3 rounded-md border border-border bg-surface text-sm text-text-main hover:bg-surface-variant focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                :aria-label="$t('Previous image')"
                @click="select(index - 1)"
            >
                {{ $t("Previous") }}
            </button>
            <button
                type="button"
                class="min-h-control min-w-11 px-3 rounded-md border border-border bg-surface text-sm text-text-main hover:bg-surface-variant focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                :aria-label="$t('Next image')"
                @click="select(index + 1)"
            >
                {{ $t("Next") }}
            </button>
        </div>

        <ul v-if="hasMultiple" class="flex flex-wrap gap-2" :aria-label="$t('Listing image gallery')">
            <li v-for="(image, imageIndex) in images" :key="image.url + imageIndex">
                <button
                    type="button"
                    class="block w-16 h-16 rounded-md overflow-hidden border focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :class="imageIndex === index ? 'border-primary' : 'border-border'"
                    :aria-label="$t('Show listing image') + ' ' + (imageIndex + 1)"
                    :aria-current="imageIndex === index ? 'true' : undefined"
                    @click="select(imageIndex)"
                >
                    <img
                        :src="image.thumbnail_url || image.url"
                        :alt="image.alt || title"
                        class="w-full h-full object-cover"
                    />
                </button>
            </li>
        </ul>
    </div>
    <div
        v-else
        class="rounded-xl border border-border bg-primary/10 aspect-[4/3] max-h-72"
        aria-hidden="true"
    />
</template>
