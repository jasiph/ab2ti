<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import LyricRenderer from "@/Components/Base/LyricRenderer.vue";

const props = defineProps({
    song: Object,
});

const isFullscreen = ref(false);
const showChords = ref(true);
const fontSize = ref(18);

const toggleFullscreen = () => {
    const elem = document.documentElement;
    if (!isFullscreen.value) {
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) { /* Safari */
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) { /* IE11 */
            elem.msRequestFullscreen();
        }
        isFullscreen.value = true;
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE11 */
            document.msExitFullscreen();
        }
        isFullscreen.value = false;
    }
};

const increaseFont = () => {
    if (fontSize.value < 60) fontSize.value += 2;
};

const decreaseFont = () => {
    if (fontSize.value > 12) fontSize.value -= 2;
};

// Simple helper to wrap chords in span if they assume [Chord] format
// If not using [Chord] format, this won't do much, but serves as a base.
const formatBody = (body) => {
    if (!showChords.value) {
        // Remove chords inside []
        return body.replace(/\[.*?\]/g, "");
    }
    // Wrap chords in span
    return body.replace(/\[(.*?)\]/g, '<span class="text-primary font-bold">[$1]</span>');
};

</script>

<template>
    <AppLayout :title="song.title">
        <template #header>
            <div class="flex justify-between items-center no-print">
                <h2 class="font-semibold text-xl text-text-main leading-tight">
                    {{ song.title }}
                </h2>
                <div class="flex gap-2">
                     <Link :href="route('admin.songs.edit', song.id)">
                        <SecondaryButton>{{ $t('Edit') }}</SecondaryButton>
                    </Link>
                    <Link :href="route('admin.songs.index')">
                        <SecondaryButton>{{ $t('Back') }}</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6" :class="{ 'fixed inset-0 z-50 bg-background overflow-y-auto p-8': isFullscreen }">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Controls -->
                <div class="bg-surface shadow p-4 rounded-md flex flex-wrap gap-4 items-center justify-between no-print transition-opacity duration-300" :class="{ 'opacity-0 hover:opacity-100': isFullscreen }">
                    <div class="flex gap-2 items-center">
                         <button @click="decreaseFont" class="p-2 border border-border rounded hover:bg-surface-variant text-text-main">A-</button>
                         <span class="text-text-main text-sm">{{ fontSize }}px</span>
                         <button @click="increaseFont" class="p-2 border border-border rounded hover:bg-surface-variant text-text-main">A+</button>
                    </div>

                    <div class="flex gap-2 items-center">
                        <label class="flex items-center space-x-2 cursor-pointer text-text-main">
                            <input type="checkbox" v-model="showChords" class="rounded border-border text-primary focus:ring-primary">
                            <span>Chords</span>
                        </label>
                    </div>

                    <div>
                        <PrimaryButton @click="toggleFullscreen">
                            {{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen / Prompter Mode' }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Song Content -->
                <div class="bg-surface shadow sm:rounded-lg p-6 sm:p-10 transition-all text-text-main">
                    <!-- Metadata Header -->
                    <div class="border-b border-border pb-6 mb-6 text-center">
                        <h1 class="font-bold mb-2 uppercase tracking-wide" :style="{ fontSize: (fontSize * 1.5) + 'px' }">{{ song.title }}</h1>
                        <p v-if="song.alternative_title" class="text-text-muted text-lg mb-4">({{ song.alternative_title }})</p>
                        
                        <div class="flex justify-center gap-6 text-sm text-text-muted uppercase tracking-wider font-semibold">
                            <span v-if="song.key">Key: {{ song.key }}</span>
                            <span v-if="song.tempo">{{ song.tempo }} BPM</span>
                            <span v-if="song.time_signature">{{ song.time_signature }}</span>
                        </div>
                         <div class="mt-2 text-xs text-text-muted">
                            <span v-if="song.lyric_author">Lyrics: {{ song.lyric_author }}</span>
                            <span v-if="song.music_author" class="ml-4">Music: {{ song.music_author }}</span>
                        </div>
                    </div>

                    <!-- Lyrics / Sections -->
                    <div class="space-y-8 font-mono leading-relaxed whitespace-pre-wrap" :style="{ fontSize: fontSize + 'px' }">
                        <div v-for="(section, index) in song.content" :key="index" class="relative">
                            <span class="absolute -left-4 -top-0 text-xs font-bold text-text-muted uppercase -translate-x-full pr-2 pt-1.5 hidden sm:block select-none">
                                {{ section.type }}
                            </span>
                            <div class="mb-1 sm:hidden text-xs font-bold text-primary uppercase tracking-wide">
                                {{ section.type }}
                            </div>
                            <!-- Custom Lyric Renderer -->
                            <LyricRenderer 
                                :content="section.body" 
                                :show-chords="showChords" 
                            />
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="mt-12 pt-6 border-t border-border text-center text-xs text-text-muted">
                        <p v-if="song.copyright">&copy; {{ song.copyright }}</p>
                        <p>Generated by Presbi App</p>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Print Styles */
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white;
        color: black;
    }
    .shadow {
        box-shadow: none !important;
    }
}
</style>
