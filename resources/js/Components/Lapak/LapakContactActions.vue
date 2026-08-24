<script setup>
defineProps({
    channels: {
        type: Array,
        default: () => [],
    },
    heading: {
        type: String,
        default: "",
    },
    emptyLabel: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <section>
        <h2 v-if="heading" class="text-lg font-semibold text-text-main">
            {{ heading }}
        </h2>
        <ul v-if="channels.length" class="mt-3 flex flex-col sm:flex-row flex-wrap gap-3">
            <li v-for="channel in channels" :key="channel.type">
                <a
                    :href="channel.href"
                    :target="channel.type === 'whatsapp' ? '_blank' : undefined"
                    :rel="channel.type === 'whatsapp' ? 'noopener noreferrer' : undefined"
                    class="inline-flex items-center justify-center min-h-control px-4 py-2.5 rounded-md text-sm font-medium bg-primary text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                >
                    {{ channel.label }}
                    <span class="sr-only">: {{ channel.display }}</span>
                </a>
            </li>
        </ul>
        <p v-else-if="emptyLabel" class="mt-3 text-sm text-text-muted leading-relaxed">
            {{ emptyLabel }}
        </p>
    </section>
</template>
