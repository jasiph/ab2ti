<script setup>
const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="bg-surface border-t border-border mt-auto">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-xs text-text-muted">
                &copy; {{ currentYear }} Startup Inc. All rights reserved.
                <span class="mx-2">|</span>
                v1.0.0
            </p>
        </div>
    </footer>
</template>
