<script setup>
import { computed } from "vue";
import { trans } from "laravel-vue-i18n";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

const props = defineProps({
    section: {
        type: Object,
        default: null,
    },
});

const displayTitle = computed(
    () => props.section?.title || trans("Welcome fallback title"),
);
const displayContent = computed(
    () => props.section?.content || trans("Welcome fallback content"),
);
const displaySecondaryContent = computed(
    () => props.section?.secondary_content || trans("Welcome fallback secondary"),
);
const displayImage = computed(() => props.section?.image_url || "");
const displayCtaLabel = computed(
    () => props.section?.cta_label || trans("Pelajari Lebih Lanjut"),
);
const displayCtaUrl = computed(
    () => props.section?.cta_url || route("about"),
);
</script>

<template>
    <PublicSection>
        <PublicContainer>
            <div
                class="grid grid-cols-1 gap-12 items-center"
                :class="displayImage ? 'md:grid-cols-2' : ''"
            >
                <div :class="displayImage ? 'order-2 md:order-1' : ''">
                    <div class="w-16 h-1 bg-secondary mb-6" aria-hidden="true" />
                    <h2 class="text-3xl md:text-4xl font-bold text-primary mb-6 leading-tight">
                        {{ displayTitle }}
                    </h2>
                    <p class="text-lg text-text-muted leading-relaxed mb-6 max-w-2xl">
                        {{ displayContent }}
                    </p>
                    <p v-if="displaySecondaryContent" class="text-lg text-text-muted leading-relaxed max-w-2xl">
                        {{ displaySecondaryContent }}
                    </p>

                    <div class="mt-8">
                        <BaseButton :href="displayCtaUrl" variant="secondary">
                            {{ displayCtaLabel }}
                        </BaseButton>
                    </div>
                </div>

                <div v-if="displayImage" class="order-1 md:order-2 relative">
                    <div class="relative rounded-2xl overflow-hidden shadow-lg aspect-[4/3] bg-surface-variant">
                        <img
                            :src="displayImage"
                            :alt="displayTitle"
                            loading="lazy"
                            class="w-full h-full object-cover object-center"
                        />
                    </div>
                </div>
            </div>
        </PublicContainer>
    </PublicSection>
</template>
