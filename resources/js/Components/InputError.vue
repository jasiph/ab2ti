<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-accent">
            {{ message }}
        </p>
    </div>
</template>
