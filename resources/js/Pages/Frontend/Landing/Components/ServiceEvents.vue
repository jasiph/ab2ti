<script setup>
defineProps({
    events: {
        type: Array,
        default: () => [
            {
                id: 1,
                title: "Bank Benih",
                date: "Program Utama",
                time: "Konservasi & Pelestarian",
                description: "Pengumpulan, karakterisasi, identifikasi, dan penyimpanan benih-benih pertanian milik petani untuk upaya konservasi benih lokal Indonesia.",
                image: "https://images.unsplash.com/photo-1416879595882-3373a0480b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
            },
            {
                id: 2,
                title: "Teknologi Tani",
                date: "Program Utama",
                time: "Riset & Pengembangan",
                description: "Penelitian dan pengembangan melalui pemurnian, seleksi, persilangan, serta pengembangan teknologi pengawetan dan pengepakan benih ramah lingkungan.",
                image: "https://images.unsplash.com/photo-1625246333195-78d9c38ad449?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
            },
            {
                id: 3,
                title: "Pendampingan Petani",
                date: "Program Utama",
                time: "Pelatihan & Advokasi",
                description: "Pelatihan teknologi perbenihan, fasilitasi program pemuliaan tanaman partisipatif, dan advokasi kebijakan untuk meningkatkan peran petani.",
                image: "https://images.unsplash.com/photo-1592982537447-6f2a6a0c7c18?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
            }
        ]
    }
});
</script>

<template>
    <section id="program" class="py-16 bg-surface-variant">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-primary mb-4">{{ $t('Program Kami') }}</h2>
                <div class="w-16 h-1 bg-secondary mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div v-for="event in events" :key="event.id" class="bg-surface rounded-xl shadow-lg border border-border overflow-hidden group hover:shadow-2xl transition duration-300">
                    <!-- Image -->
                    <div class="h-48 overflow-hidden relative">
                        <img :src="event.image" :alt="event.title" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" />
                        <div class="absolute top-4 left-4 bg-secondary text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                            {{ $t(event.date) }}
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <div class="flex items-center text-sm text-text-muted mb-3">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                            </svg>
                            {{ $t(event.time) }}
                        </div>
                        <h3 class="text-xl font-bold text-text-main mb-3 group-hover:text-primary transition">
                            {{ $t(event.title) }}
                        </h3>
                        <p class="text-text-muted text-sm leading-relaxed mb-4">
                            {{ $t(event.description) }}
                        </p>
                        <a href="#" class="inline-flex items-center text-primary text-sm font-semibold hover:underline">
                            {{ $t('Selengkapnya') }}
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
