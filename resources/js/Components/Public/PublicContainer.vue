<script setup>
defineProps({
    width: {
        type: String,
        default: "7xl",
    },
});

const widthClass = {
    "5xl": "max-w-5xl",
    "7xl": "max-w-7xl",
    prose: "max-w-3xl",
};
</script>

<template>
    <div
        class="mx-auto w-full px-4 sm:px-6 lg:px-8"
        :class="widthClass[width] || widthClass['7xl']"
    >
        <slot />
    </div>
</template>
