<script setup>
import { computed } from "vue";
import { parseChords } from "@/Utils/ChordParser";

const props = defineProps({
    content: {
        type: String,
        default: "",
    },
    showChords: {
        type: Boolean,
        default: true,
    },
});

const lines = computed(() => {
    return parseChords(props.content);
});
</script>

<template>
    <div class="font-mono text-text-main">
        <div v-for="(line, lineIndex) in lines" :key="lineIndex" class="flex flex-wrap min-h-[1.5em] mb-1">
            <div 
                v-for="(segment, segIndex) in line" 
                :key="segIndex" 
                class="flex flex-col group mr-0.5"
            >
                <!-- Chord Row -->
                <!-- Only render if showChords is true -->
                <!-- Use visibility: hidden if we want to preserve layout but hide text? No, user wants remove space. -->
                <!-- So v-if is correct. -->
                <div 
                    v-if="showChords"
                    class="text-primary font-bold text-sm leading-none mb-0.5 select-none h-4"
                >
                    {{ segment.chord || '&nbsp;' }}
                </div>
                
                <!-- Lyric Row -->
                 <!-- Use whitespace-pre to preserve spaces inside the lyric token -->
                <div class="leading-snug whitespace-pre">
                    {{ segment.lyric || (segment.chord ? '' : '&nbsp;') }}
                </div>
            </div>
        </div>
    </div>
</template>
