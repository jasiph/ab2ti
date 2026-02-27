<script setup>
import { ref, watch, onMounted, onUnmounted } from "vue";
import { debounce } from "@/Utils/debounce";
import axios from "axios";

const props = defineProps({
    modelValue: [String, Number],
    endpoint: {
        type: String,
        required: true,
    },
    label: String,
    placeholder: {
        type: String,
        default: "Search...",
    },
    initialLabel: {
        type: String,
        default: "",
    },
    error: String,
});

const emit = defineEmits(["update:modelValue"]);

const searchQuery = ref("");
const results = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const containerRef = ref(null);

// Initialize search query with initial label if provided
watch(
    () => props.initialLabel,
    (newLabel) => {
        if (newLabel && !searchQuery.value) {
            searchQuery.value = newLabel;
        }
    },
    { immediate: true }
);

const fetchResults = debounce(async (query) => {
    if (!query) {
        results.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get(props.endpoint, {
            params: { search: query },
        });
        results.value = response.data;
    } catch (error) {
        console.error("Search failed:", error);
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

const onInput = () => {
    isOpen.value = true;
    // If user clears input, clear selection
    if (!searchQuery.value) {
        emit("update:modelValue", null);
    }
    fetchResults(searchQuery.value);
};

const selectItem = (item) => {
    searchQuery.value = item.name + (item.unit_number ? ` (${item.unit_number})` : '');
    emit("update:modelValue", item.id);
    isOpen.value = false;
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <label v-if="label" class="block font-medium text-sm text-text-main mb-1">
            {{ label }}
        </label>

        <div class="relative">
            <input
                type="text"
                v-model="searchQuery"
                @input="onInput"
                @focus="isOpen = true"
                :placeholder="placeholder"
                class="border-border bg-surface text-text-main focus:border-primary focus:ring-primary rounded-md shadow-sm block w-full transition sm:text-sm placeholder-text-muted/50"
                :class="{
                    'border-red-500 focus:border-red-500 focus:ring-red-500': error,
                }"
            />
            
            <!-- Loading Indicator -->
            <div v-if="isLoading" class="absolute right-3 top-2.5">
                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <p v-if="error" class="text-sm text-red-600 mt-1 animate-pulse">
            {{ error }}
        </p>

        <!-- Dropdown Results -->
        <div
            v-if="isOpen && (results.length > 0 || searchQuery)"
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
        >
            <ul v-if="results.length > 0">
                <li
                    v-for="item in results"
                    :key="item.id"
                    @click="selectItem(item)"
                    class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500 text-gray-900 dark:text-gray-100"
                >
                    <div class="flex items-center">
                        <span class="font-normal block truncate">
                            {{ item.name }}
                        </span>
                        <span v-if="item.unit_number" class="ml-2 text-gray-500 dark:text-gray-400 text-xs truncate group-hover:text-indigo-200">
                           {{ item.unit_number }}
                        </span>
                    </div>
                </li>
            </ul>
             <div v-else-if="searchQuery && !isLoading" class="py-2 px-3 text-gray-500 text-sm italic">
                No results found.
            </div>
        </div>
    </div>
</template>
