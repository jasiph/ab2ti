<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    hint: String,
    error: String,
    accept: String,
    required: Boolean,
});

const emit = defineEmits(["change"]);

const selectedName = ref("");

const describedBy = computed(() => {
    if (props.error) {
        return `${props.id}-error`;
    }

    if (props.hint) {
        return `${props.id}-hint`;
    }

    return undefined;
});

const onChange = (event) => {
    const file = event.target.files?.[0] || null;
    selectedName.value = file?.name || "";
    emit("change", file);
};
</script>

<template>
    <div class="w-full">
        <label :for="id" class="block font-medium text-sm text-text-main mb-1">
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <input
            :id="id"
            type="file"
            :accept="accept"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            class="block w-full min-h-control text-base sm:text-sm text-text-main file:mr-3 file:min-h-control file:px-4 file:rounded-md file:border-0 file:bg-primary file:text-white file:font-medium"
            @change="onChange"
        />

        <p v-if="selectedName && !error" class="text-sm text-text-muted mt-1">
            {{ selectedName }}
        </p>

        <p v-if="hint && !error" :id="`${id}-hint`" class="text-sm text-text-muted mt-1">
            {{ hint }}
        </p>

        <p v-if="error" :id="`${id}-error`" class="text-sm text-danger mt-1" role="alert">
            {{ error }}
        </p>
    </div>
</template>
