<script setup>
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

defineProps({
    applyUrl: String,
});
</script>

<template>
    <FrontendLayout :title="$t('Membership')">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16">
            <p class="text-sm font-medium text-primary mb-3">
                {{ $t("Membership") }}
            </p>
            <h1 class="text-3xl sm:text-4xl font-bold leading-tight text-text-main">
                {{ $t("Join AB2TI as a member") }}
            </h1>
            <div class="mt-6 space-y-4 text-base sm:text-lg text-text-muted leading-relaxed">
                <p>
                    {{ $t("Membership landing intro") }}
                </p>
                <p>
                    {{ $t("Membership is not cooperative membership") }}
                </p>
                <p>
                    {{ $t("Membership apply does not create an account") }}
                </p>
                <p>
                    {{ $t("Submitting is not membership approval") }}
                </p>
            </div>

            <ol class="mt-8 space-y-3 text-base text-text-muted leading-relaxed">
                <li>
                    <span class="font-medium text-text-main">1.</span>
                    {{ $t("Membership step fill") }}
                </li>
                <li>
                    <span class="font-medium text-text-main">2.</span>
                    {{ $t("Membership step send") }}
                </li>
                <li>
                    <span class="font-medium text-text-main">3.</span>
                    {{ $t("Membership step review") }}
                </li>
            </ol>

            <div class="mt-10">
                <BaseButton :href="applyUrl" size="lg">
                    {{ $t("Apply for membership") }}
                </BaseButton>
            </div>

            <p class="mt-6 text-sm text-text-muted">
                {{ $t("Staff will review after you submit") }}
            </p>
        </div>
    </FrontendLayout>
</template>
