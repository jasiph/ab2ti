<script setup>
import BaseButton from "@/Components/Base/BaseButton.vue";
import PublicContainer from "@/Components/Public/PublicContainer.vue";
import PublicSection from "@/Components/Public/PublicSection.vue";

defineProps({
    cta: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <PublicSection>
        <PublicContainer>
            <div class="bg-surface border border-border rounded-xl p-6 sm:p-8">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold text-text-main leading-tight">
                        {{ $t("Join AB2TI as a member") }}
                    </h2>
                    <p class="mt-2 text-base text-text-muted leading-relaxed">
                        {{ $t("Membership landing intro") }}
                    </p>
                    <p class="mt-2 text-sm text-text-muted leading-relaxed">
                        {{ $t("Membership is not cooperative membership") }}
                    </p>
                </div>
                <div class="mt-6 flex flex-col sm:flex-row flex-wrap gap-3">
                    <BaseButton :href="cta.learn_url || cta.url || route('membership.index')" size="lg">
                        {{ $t("Learn about membership") }}
                    </BaseButton>
                    <BaseButton
                        :href="cta.apply_url || route('membership.apply')"
                        variant="secondary"
                        size="lg"
                    >
                        {{ $t("Apply for membership") }}
                    </BaseButton>
                </div>
            </div>
        </PublicContainer>
    </PublicSection>
</template>
