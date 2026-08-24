<script setup>
import { computed, ref, watch } from "vue";
import BaseModal from "@/Components/Base/BaseModal.vue";
import axios from "axios";

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(["close", "select"]);

const blocks = ref([]);
const loading = ref(false);
const searchQuery = ref("");

const fetchBlocks = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route("admin.block-templates.active"));
        blocks.value = response.data;
    } catch (error) {
        console.error("Gagal mengambil daftar block template", error);
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.show,
    (newVal) => {
        if (newVal && blocks.value.length === 0) {
            fetchBlocks();
        }
    },
);

const filteredBlocks = computed(() => {
    if (!searchQuery.value) {
        return blocks.value;
    }

    const lowerQuery = searchQuery.value.toLowerCase();

    return blocks.value.filter(
        (b) =>
            b.name.toLowerCase().includes(lowerQuery) ||
            (b.description && b.description.toLowerCase().includes(lowerQuery)),
    );
});

const selectBlock = (block) => {
    emit("select", block);
    emit("close");
};
</script>

<template>
    <BaseModal :show="show" labelled-by="block-picker-title" maxWidth="2xl" @close="$emit('close')">
        <div class="bg-white dark:bg-gray-800 overflow-hidden flex flex-col h-[80vh] max-h-[600px]">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
                <h3 id="block-picker-title" class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-xl">✨</span> Sisipkan Dynamic Block
                </h3>
            </div>

            <div class="p-4 flex flex-col flex-1 overflow-hidden">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari nama block template..."
                    class="w-full mb-4 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                />

                <div v-if="loading" class="flex-1 flex justify-center items-center text-gray-500 dark:text-gray-400">
                    <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <div v-else-if="filteredBlocks.length === 0" class="flex-1 flex flex-col justify-center items-center text-gray-500 dark:text-gray-400 pb-10">
                    <svg class="w-16 h-16 text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <p class="text-lg">Tidak ada block template yang ditemukan.</p>
                </div>

                <div v-else class="flex-1 overflow-y-auto pr-2 grid grid-cols-1 sm:grid-cols-2 gap-4 auto-rows-max">
                    <div
                        v-for="block in filteredBlocks"
                        :key="block.id"
                        @click="selectBlock(block)"
                        class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 cursor-pointer hover:border-indigo-500 hover:ring-1 hover:ring-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 transition flex flex-col h-full bg-white dark:bg-gray-800 shadow-sm"
                    >
                        <h4 class="font-bold text-gray-900 dark:text-white text-lg flex items-center gap-2 mb-2">
                            <span>📄</span> {{ block.name }}
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 flex-1 leading-relaxed">
                            {{ block.description || "Tidak ada deskripsi rinci untuk block ini." }}
                        </p>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">
                                Pilih & Sisipkan &rarr;
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-right">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium"
                >
                    Batal
                </button>
            </div>
        </div>
    </BaseModal>
</template>
