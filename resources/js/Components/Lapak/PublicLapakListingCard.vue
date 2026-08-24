<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    listing: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <article class="bg-surface border border-border rounded-xl overflow-hidden shadow-sm h-full flex flex-col">
        <Link
            :href="listing.url"
            class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-focus h-full flex flex-col"
        >
            <div class="aspect-[4/3] bg-surface-variant overflow-hidden">
                <img
                    v-if="listing.image_url"
                    :src="listing.image_url"
                    :alt="listing.image_alt || listing.title"
                    loading="lazy"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-primary/10" aria-hidden="true" />
            </div>
            <div class="p-4 sm:p-5 flex-1 flex flex-col gap-2 min-w-0">
                <p v-if="listing.category_name" class="text-xs font-medium text-primary">
                    {{ listing.category_name }}
                </p>
                <h3 class="text-lg font-semibold text-text-main leading-snug break-words">
                    {{ listing.title }}
                </h3>
                <p class="text-base font-medium text-text-main">
                    {{ listing.price_label }}
                </p>
                <p v-if="listing.lapak_name" class="text-sm text-text-muted leading-relaxed break-words">
                    {{ listing.lapak_name }}
                    <span v-if="listing.location_text"> · {{ listing.location_text }}</span>
                </p>
                <p class="mt-auto pt-2 text-sm text-primary">
                    {{ $t("View listing") }}
                </p>
            </div>
        </Link>
    </article>
</template>
