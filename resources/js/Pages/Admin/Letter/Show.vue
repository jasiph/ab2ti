<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { useConfirm } from "@/Composables/useConfirm";
import { computed } from "vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const props = defineProps({
    letter: Object,
});

const previewContent = computed(() => {
    let content = props.letter.template.content_template;
    const data = props.letter.content_data;
    
    // Simple substitution {{ key }} -> value
    Object.keys(data).forEach(key => {
        const regex = new RegExp(`{{ ${key} }}`, 'g'); // Matches {{ key }}
        content = content.replace(regex, data[key]);
    });
    
    // Also replace {{ letter_number }} if available
    if (props.letter.letter_number) {
        content = content.replace(/{{ letter_number }}/g, props.letter.letter_number);
    } else {
        content = content.replace(/{{ letter_number }}/g, '[DRAFT NUMBER]');
    }

    return content;
});

const { confirm } = useConfirm();

const approve = async () => {
    const ok = await confirm({
        title: 'Approve Letter',
        message: 'Are you sure you want to approve and publish this letter? This action cannot be undone.',
        confirmText: 'Approve & Publish',
        variant: 'warning',
    });
    if (ok) {
        router.post(route("admin.letters.approve", props.letter.id));
    }
};

const print = () => {
    window.open(route("admin.letters.print", props.letter.id), '_blank');
};
</script>

<template>
    <AppLayout title="View Letter">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Letter Details
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('admin.letters.index')">
                        <SecondaryButton>Back</SecondaryButton>
                    </Link>
                    <PrimaryButton
                        v-if="letter.status === 'draft' || letter.status === 'pending_review'"
                        @click="approve"
                        class="bg-green-600 hover:bg-green-700 focus:ring-green-500"
                    >
                        Approve & Publish
                    </PrimaryButton>
                    <SecondaryButton
                        v-if="letter.status === 'approved'"
                        @click="print"
                    >
                        Print / Download PDF
                    </SecondaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Status Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Status</span>
                            <div class="mt-1">
                                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 rounded-md font-semibold text-sm">
                                    {{ letter.status.toUpperCase() }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Letter Number</span>
                            <div class="mt-1 text-lg font-mono dark:text-gray-200">
                                {{ letter.letter_number || '-' }}
                            </div>
                        </div>
                         <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold">Template</span>
                            <div class="mt-1 dark:text-gray-200">
                                {{ letter.template.name }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-8">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">Document Preview</h3>
                    <div class="prose max-w-none border border-gray-200 dark:border-gray-700 p-8 shadow-sm min-h-[400px] bg-white text-black">
                        <div v-html="previewContent"></div>
                    </div>
                </div>
                
                 <!-- Debug Data (Optional) -->
                 <!-- <pre class="mt-4 text-xs">{{ letter.content_data }}</pre> -->
            </div>
        </div>
    </AppLayout>
</template>
