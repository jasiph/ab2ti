<script setup>
import FrontendLayout from "@/Layouts/FrontendLayout.vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    actions: {
        type: Array,
        default: () => [],
    },
});

const runClientAction = (action) => {
    if (action.reload) {
        window.location.reload();
        return;
    }

    if (action.back) {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        window.location.assign("/");
    }
};
</script>

<template>
    <FrontendLayout :title="title">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <p class="text-sm font-semibold text-primary tracking-wide">
                {{ status }}
            </p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-text-main leading-tight">
                {{ title }}
            </h1>
            <p class="mt-4 text-base text-text-muted leading-relaxed">
                {{ message }}
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <template v-for="(action, index) in actions" :key="index">
                    <BaseButton
                        v-if="action.href"
                        :href="action.href"
                        :variant="index === 0 ? 'primary' : 'secondary'"
                    >
                        {{ action.label }}
                    </BaseButton>
                    <BaseButton
                        v-else
                        type="button"
                        :variant="index === 0 ? 'primary' : 'secondary'"
                        @click="runClientAction(action)"
                    >
                        {{ action.label }}
                    </BaseButton>
                </template>
            </div>
        </div>
    </FrontendLayout>
</template>
