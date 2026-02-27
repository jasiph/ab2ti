<script setup>
import { onMounted, ref } from "vue";

defineProps({
    modelValue: String,
    label: String,
    type: { type: String, default: "text" },
    error: String,
    placeholder: String,
    id: {
        type: String,
        default: () => `input-${Math.random().toString(36).substr(2, 9)}`,
    },
    required: Boolean,
});

defineEmits(["update:modelValue"]);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute("autofocus")) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div class="w-full">
        <label
            v-if="label"
            :for="id"
            class="block font-medium text-sm text-text-main mb-1"
        >
            {{ label }} <span v-if="required" class="text-red-500">*</span>
        </label>

        <input
            :id="id"
            ref="input"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            @input="$emit('update:modelValue', $event.target.value)"
            class="border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm block w-full transition sm:text-sm placeholder-text-muted/50"
            :class="{
                'border-red-500 focus:border-red-500 focus:ring-red-500': error,
            }"
        />

        <p v-if="error" class="text-sm text-red-600 mt-1 animate-pulse">
            {{ error }}
        </p>
    </div>
</template>
