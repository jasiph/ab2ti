<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    icon: {
        type: String, // SVG path or component name if dynamic
        required: false,
    },
    color: {
        type: String,
        default: 'bg-indigo-500', // Default color class
    },
    href: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center transition hover:shadow-md"
    >
        <div 
            class="p-3 rounded-full mr-4 text-white"
            :class="color"
        >
            <slot name="icon">
                <svg v-if="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
                </svg>
            </slot>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ title }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ value }}
            </div>
        </div>
    </component>
</template>
