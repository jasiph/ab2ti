<script setup>
import { computed, onMounted, ref, useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

defineProps({
    modelValue: String,
    label: String,
    error: String,
    placeholder: String,
    hint: String,
    rows: { type: Number, default: 4 },
    id: {
        type: String,
        default: () => `textarea-${Math.random().toString(36).substr(2, 9)}`,
    },
    required: Boolean,
});

defineEmits(["update:modelValue"]);

const attrs = useAttrs();
const textarea = ref(null);

const wrapperClass = computed(() => attrs.class);
const controlAttrs = computed(() => {
    const { class: _ignored, ...rest } = attrs;

    return rest;
});

onMounted(() => {
    if (textarea.value?.hasAttribute("autofocus")) {
        textarea.value.focus();
    }
});

defineExpose({ focus: () => textarea.value?.focus() });
</script>

<template>
    <div class="w-full" :class="wrapperClass">
        <label
            v-if="label"
            :for="id"
            class="block font-medium text-sm text-text-main mb-1"
        >
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <textarea
            :id="id"
            ref="textarea"
            v-bind="controlAttrs"
            :value="modelValue"
            :placeholder="placeholder"
            :rows="rows"
            :aria-invalid="error ? 'true' : undefined"
            :aria-required="required ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
            @input="$emit('update:modelValue', $event.target.value)"
            class="border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm block w-full min-h-control transition text-base sm:text-sm placeholder-text-muted/50"
            :class="{
                'border-danger focus:border-danger focus:ring-danger': error,
            }"
        />

        <p v-if="hint && !error" :id="`${id}-hint`" class="text-sm text-text-muted mt-1">
            {{ hint }}
        </p>

        <p v-if="error" :id="`${id}-error`" class="text-sm text-danger mt-1" role="alert">
            {{ error }}
        </p>
    </div>
</template>
