<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { trans } from "laravel-vue-i18n";

defineProps({
    congregation: Object,
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <AppLayout :title="trans('Member Details')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ trans('Member Details') }}
                </h2>
                <div class="space-x-2">
                     <Link
                        :href="route('admin.congregations.index')"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition"
                    >
                        {{ trans('Back') }}
                    </Link>
                    <Link
                        :href="route('admin.congregations.edit', congregation.id)"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition"
                    >
                        {{ trans('Edit') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Profile Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Photo -->
                        <div class="flex-shrink-0">
                             <div v-if="congregation.photo_path" class="h-32 w-32 rounded-lg overflow-hidden">
                                     <img :src="congregation.photo_path" alt="Photo" class="h-full w-full object-cover" />
                                </div>
                                <div v-else class="h-32 w-32 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-500 font-bold text-4xl">
                                    {{ congregation.name.charAt(0) }}
                                </div>
                        </div>

                        <!-- Info -->
                        <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ congregation.name }}</h3>
                                <p class="text-gray-500 dark:text-gray-400 mb-4">{{ congregation.unit_number }} <span v-if="congregation.nik">| NIK: {{ congregation.nik }}</span></p>
                                
                                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                    <p><span class="font-semibold w-32 inline-block">{{ trans('Gender') }}:</span> {{ trans(congregation.gender) }}</p>
                                    <p><span class="font-semibold w-32 inline-block">{{ trans('Birth') }}:</span> {{ congregation.place_of_birth }}, {{ formatDate(congregation.date_of_birth) }}</p>
                                    <p><span class="font-semibold w-32 inline-block">{{ trans('Status') }}:</span> <span class="uppercase">{{ trans(congregation.status) }}</span></p>
                                    <p><span class="font-semibold w-32 inline-block">{{ trans('Marital Status') }}:</span> {{ trans(congregation.marital_status) }}</p>
                                </div>
                            </div>
                            
                            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                <h4 class="font-semibold text-lg mb-2 border-b">{{ trans('Contact') }}</h4>
                                <p><span class="font-semibold w-24 inline-block">{{ trans('Phone') }}:</span> {{ congregation.phone || '-' }}</p>
                                <p><span class="font-semibold w-24 inline-block">{{ trans('Email') }}:</span> {{ congregation.email || '-' }}</p>
                                <p><span class="font-semibold w-24 inline-block">{{ trans('Address') }}:</span> {{ congregation.address || '-' }}</p>

                                <h4 class="font-semibold text-lg mt-4 mb-2 border-b">{{ trans('Church Data') }}</h4>
                                <p><span class="font-semibold w-24 inline-block">{{ trans('Baptism') }}:</span> {{ formatDate(congregation.baptism_date) }}</p>
                                <p><span class="font-semibold w-24 inline-block">{{ trans('Sidi') }}:</span> {{ formatDate(congregation.sidi_date) }}</p>
                                <p><span class="font-semibold w-24 inline-block">{{ trans('Join Date') }}:</span> {{ formatDate(congregation.join_date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Family Tree / Relationships -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ trans('Family Tree') }}</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                        <!-- Parents -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">{{ trans('Parents') }}</h4>
                            
                            <div v-if="congregation.father" class="p-3 border rounded-md dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                <Link :href="route('admin.congregations.show', congregation.father.id)" class="text-indigo-600 hover:underline font-medium">
                                    {{ congregation.father.name }}
                                </Link>
                                <div class="text-xs text-gray-500">{{ trans('Father') }}</div>
                            </div>
                             <div v-else class="text-sm text-gray-400 italic">{{ trans('No Father Data') }}</div>

                            <div v-if="congregation.mother" class="p-3 border rounded-md dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                <Link :href="route('admin.congregations.show', congregation.mother.id)" class="text-indigo-600 hover:underline font-medium">
                                     {{ congregation.mother.name }}
                                </Link>
                                <div class="text-xs text-gray-500">{{ trans('Mother') }}</div>
                            </div>
                            <div v-else class="text-sm text-gray-400 italic">{{ trans('No Mother Data') }}</div>
                        </div>

                        <!-- Current & Spouse -->
                        <div class="space-y-4 relative">
                           <!-- Connector Line visual could go here -->
                           <div class="p-4 border-2 border-indigo-500 rounded-md bg-indigo-50 dark:bg-indigo-900/20">
                                <div class="font-bold text-lg">{{ congregation.name }}</div>
                                <div class="text-xs text-indigo-500">{{ trans('Current') }}</div>
                           </div>

                           <div v-if="congregation.spouse" class="p-3 border rounded-md dark:border-gray-700 bg-gray-50 dark:bg-gray-900 mt-4">
                                <Link :href="route('admin.congregations.show', congregation.spouse.id)" class="text-indigo-600 hover:underline font-medium">
                                    {{ congregation.spouse.name }}
                                </Link>
                                <div class="text-xs text-gray-500">{{ trans('Spouse') }}</div>
                            </div>
                        </div>

                        <!-- Children -->
                        <div class="space-y-2">
                             <h4 class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">{{ trans('Children') }}</h4>
                             
                             <div v-if="congregation.children && congregation.children.length > 0" class="space-y-2">
                                <div v-for="child in congregation.children" :key="child.id" class="p-3 border rounded-md dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                     <Link :href="route('admin.congregations.show', child.id)" class="text-indigo-600 hover:underline font-medium">
                                        {{ child.name }}
                                    </Link>
                                </div>
                             </div>
                             <div v-else class="text-sm text-gray-400 italic">{{ trans('No Children Recorded') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
