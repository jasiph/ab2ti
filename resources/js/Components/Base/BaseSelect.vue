<script setup>
defineProps({
    modelValue: [String, Number],
    options: {
        type: [Array, Object],
        default: () => [],
    },
    placeholder: {
        type: String,
        default: "Pilih salah satu",
    },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <select
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)"
            class="w-full border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm min-h-control transition duration-150 ease-in-out text-base sm:text-sm placeholder-text-muted"
    >
        <option value="" disabled selected>{{ placeholder }}</option>

        <template
            v-if="Array.isArray(options) && typeof options[0] !== 'object'"
        >
            <option v-for="option in options" :key="option" :value="option">
                {{ option }}
            </option>
        </template>

        <template v-else>
            <option
                v-for="option in options"
                :key="option.id || option.value"
                :value="option.id || option.value"
            >
                {{ option.name || option.label }}
            </option>
        </template>
    </select>
</template>
