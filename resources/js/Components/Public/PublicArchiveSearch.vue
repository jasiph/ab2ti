<script setup>
import { computed } from "vue";
import BaseButton from "@/Components/Base/BaseButton.vue";

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    q: {
        type: String,
        default: "",
    },
    label: {
        type: String,
        required: true,
    },
    submitLabel: {
        type: String,
        required: true,
    },
    clearHref: {
        type: String,
        default: "",
    },
    clearLabel: {
        type: String,
        default: "",
    },
    extraFields: {
        type: Array,
        default: () => [],
    },
});

const shouldShowClear = computed(() => {
    if (!props.clearHref) {
        return false;
    }

    if (props.q) {
        return true;
    }

    return props.extraFields.some((field) => Boolean(field?.value));
});
</script>

<template>
    <form
        method="get"
        :action="action"
        role="search"
        class="mt-6 flex flex-col sm:flex-row gap-3 sm:items-end"
    >
        <div class="flex-1 min-w-0">
            <label :for="'archive-search'" class="block font-medium text-sm text-text-main mb-1">
                {{ label }}
            </label>
            <input
                id="archive-search"
                type="search"
                name="q"
                :value="q"
                :placeholder="label"
                maxlength="100"
                class="border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm block w-full min-h-control transition text-base sm:text-sm placeholder-text-muted/50"
            />
            <input
                v-for="field in extraFields"
                :key="field.name"
                type="hidden"
                :name="field.name"
                :value="field.value"
            />
        </div>
        <div class="flex flex-wrap gap-2">
            <BaseButton type="submit">
                {{ submitLabel }}
            </BaseButton>
            <BaseButton
                v-if="shouldShowClear"
                :href="clearHref"
                variant="secondary"
            >
                {{ clearLabel }}
            </BaseButton>
        </div>
    </form>
</template>
