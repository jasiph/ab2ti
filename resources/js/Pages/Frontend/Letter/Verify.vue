<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    letter: Object,
    isValid: Boolean,
});
</script>

<template>
    <Head title="Document Verification" />
    
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div :class="['p-6 text-center', isValid ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20']">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full" :class="isValid ? 'bg-green-100 dark:bg-green-800' : 'bg-red-100 dark:bg-red-800'">
                    <svg v-if="isValid" class="h-8 w-8 text-green-600 dark:text-green-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="h-8 w-8 text-red-600 dark:text-red-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="mt-4 text-2xl font-bold" :class="isValid ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100'">
                    {{ isValid ? 'Dokumen Valid' : 'Dokumen Tidak Valid' }}
                </h2>
                <p class="mt-2 text-sm" :class="isValid ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                    {{ isValid ? 'Dokumen ini terdaftar resmi dalam sistem administrasi gereja.' : 'Dokumen tidak ditemukan atau data tidak cocok.' }}
                </p>
            </div>

            <!-- Body -->
            <div v-if="isValid && letter" class="p-6">
                <dl class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-2">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Nomor Surat</dt>
                        <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ letter.letter_number }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-2">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Tanggal Terbit</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ new Date(letter.approved_at).toLocaleString() }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-2">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-gray-900 dark:text-gray-100 capitalize">{{ letter.status }}</dd>
                    </div>
                </dl>

                <!-- Rendered Content Preview -->
                <div v-if="letter.rendered_content" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 w-full">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 text-center">Isi Dokumen</h3>
                    <div class="prose dark:prose-invert max-w-none text-xs sm:text-sm border border-gray-100 dark:border-gray-700 p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 shadow-inner overflow-auto max-h-[500px]">
                        <div v-html="letter.rendered_content"></div>
                    </div>
                    <p class="text-xs text-center text-gray-400 mt-2 italic">
                        * Konten di atas adalah data asli yang tersimpan di sistem gereja.
                    </p>
                </div>

                 <div class="pt-6 text-xs text-gray-400 dark:text-gray-500 text-center break-all">
                    Digital Signature: {{ letter.security_hash ? letter.security_hash.substring(0, 20) + '...' : '-' }}
                </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 text-center">
                 <p class="text-xs text-gray-500 dark:text-gray-300">Sistem Administrasi Gereja &copy; {{ new Date().getFullYear() }}</p>
            </div>
        </div>
    </div>
</template>
