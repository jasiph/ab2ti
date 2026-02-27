<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import DashboardStatCard from "@/Components/DashboardStatCard.vue";
import { Link } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";

defineProps({
    stats: Object,
    recentMembers: Array,
    upcomingBirthdays: Array,
});
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ trans("Dashboard") }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Primary Stats (Congregation) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <DashboardStatCard 
                        :title="trans('Total Congregation')" 
                        :value="stats.totalCongregation" 
                        color="bg-indigo-500"
                        icon="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 5.472m0 0a9.09 9.09 0 00-3.26.696 3 3 0 004.846 3.024M18.75 3a2.25 2.25 0 012.25 2.25c0 1.258-1.55 2.058-2.512 2.766-.48.354-.48 1.056 0 1.41.962.708 2.512 1.508 2.512 2.766h-5.25a2.25 2.25 0 01-2.25-2.25V3.75a2.25 2.25 0 012.25-2.25h3zm-9 0a2.25 2.25 0 00-2.25 2.25v8.25a2.25 2.25 0 002.25 2.25h5.25c0-1.258 1.55-2.058 2.512-2.766.48-.354.48-1.056 0-1.41-.962-.708-2.512-1.508-2.512-2.766V5.25A2.25 2.25 0 0012.75 3h-3z"
                    />
                     <DashboardStatCard 
                        :title="trans('Head of Families')" 
                        :value="stats.totalFamilies" 
                        color="bg-blue-500"
                        icon="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                    />
                    <DashboardStatCard 
                        :title="trans('Male')" 
                        :value="stats.totalMale" 
                        color="bg-sky-500"
                        icon="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                    />
                    <DashboardStatCard 
                        :title="trans('Female')" 
                        :value="stats.totalFemale" 
                        color="bg-pink-500"
                        icon="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.546-3.118 1.557-4.582M3 12c0 .886.216 1.722.599 2.475"
                    />
                </div>

                <!-- Secondary Stats (Features) -->
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <DashboardStatCard 
                        :title="trans('Pending Letters')" 
                        :value="stats.pendingLetters" 
                        color="bg-yellow-500"
                        icon="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                    />
                     <DashboardStatCard 
                        :title="trans('Total Documents')" 
                        :value="stats.totalDocuments" 
                        color="bg-gray-500"
                        icon="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                    />
                    <DashboardStatCard 
                        :title="trans('Total Songs')" 
                        :value="stats.totalSongs" 
                        color="bg-purple-500"
                        icon="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25a2.25 2.25 0 00-2.25-2.25H13.5a2.25 2.25 0 00-2.25 2.25v10.5a2.25 2.25 0 002.25 2.25h.493a2.25 2.25 0 002.25-2.25v-1.353"
                    />
                </div>

                <!-- Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Members -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ trans('Newest Members') }}</h3>
                            <Link :href="route('admin.congregations.index')" class="text-sm text-indigo-500 hover:text-indigo-700">{{ trans('View All') }}</Link>
                        </div>
                        <div class="space-y-4">
                            <div v-for="member in recentMembers" :key="member.id" class="flex items-center justify-between border-b last:border-0 border-gray-100 dark:border-gray-700 pb-3 last:pb-0">
                                <div class="flex items-center">
                                    <div v-if="member.photo_path" class="h-10 w-10 rounded-full overflow-hidden mr-3">
                                         <img :src="member.photo_path" alt="Photo" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 font-bold text-sm mr-3">
                                        {{ member.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ member.name }}</div>
                                        <div class="text-xs text-gray-500">{{ new Date(member.created_at).toLocaleDateString() }}</div>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                    {{ member.status ? trans(member.status.charAt(0).toUpperCase() + member.status.slice(1)) : '' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Birthdays (Reusing Logic but in list form) -->
                     <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ trans('This Month Birthdays') }}</h3>
                             <Link :href="route('admin.congregations.index')" class="text-sm text-pink-500 hover:text-pink-700">{{ trans('View All') }}</Link>
                        </div>
                        <div class="space-y-4">
                            <div v-if="upcomingBirthdays.length === 0" class="text-gray-500 text-sm italic">
                                {{ trans('No upcoming birthdays this month.') }}
                            </div>
                            <div v-else v-for="member in upcomingBirthdays" :key="member.id" class="flex items-center justify-between border-b last:border-0 border-gray-100 dark:border-gray-700 pb-3 last:pb-0">
                                <div class="flex items-center">
                                    <div v-if="member.photo_path" class="h-10 w-10 rounded-full overflow-hidden mr-3">
                                         <img :src="member.photo_path" alt="Photo" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else class="h-10 w-10 rounded-full bg-pink-100 flex items-center justify-center text-pink-500 font-bold text-sm mr-3">
                                        {{ member.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ member.name }}</div>
                                        <div class="text-xs text-gray-500">
                                             {{ new Date(member.date_of_birth).getDate() }} {{ new Date().toLocaleString('default', { month: 'short' }) }}
                                             ({{ new Date().getFullYear() - new Date(member.date_of_birth).getFullYear() }} {{ trans('Years') }})
                                        </div>
                                    </div>
                                </div>
                                <Link :href="route('admin.congregations.show', member.id)" class="text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Link :href="route('admin.congregations.create')" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg p-4 text-center transition shadow flex flex-col items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mb-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                        </svg>
                        <span class="font-semibold text-sm">{{ trans('Add Member') }}</span>
                    </Link>
                     <Link :href="route('admin.letters.create')" class="bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg p-4 text-center transition shadow flex flex-col items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mb-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <span class="font-semibold text-sm">{{ trans('Create Letter') }}</span>
                    </Link>
                     <Link :href="route('admin.songs.create')" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg p-4 text-center transition shadow flex flex-col items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mb-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25a2.25 2.25 0 00-2.25-2.25H13.5a2.25 2.25 0 00-2.25 2.25v10.5a2.25 2.25 0 002.25 2.25h.493a2.25 2.25 0 002.25-2.25v-1.353" />
                        </svg>
                        <span class="font-semibold text-sm">{{ trans('Add Song') }}</span>
                    </Link>
                     <Link :href="route('admin.documents.create')" class="bg-gray-600 hover:bg-gray-700 text-white rounded-lg p-4 text-center transition shadow flex flex-col items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mb-2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <span class="font-semibold text-sm">{{ trans('Upload Document') }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
