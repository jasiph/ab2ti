<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { trans } from "laravel-vue-i18n";
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ trans("Dashboard") }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-8 space-y-3">
                    <h3 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        AB2TI 2.0
                    </h3>
                    <p class="text-lg text-gray-700 dark:text-gray-300">
                        Foundation Dashboard
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Platform foundation is ready for AB2TI development.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
