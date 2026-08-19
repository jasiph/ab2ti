import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";

export function useNavigation() {
    const page = usePage();
    const userPermissions = computed(
        () => page.props.auth.user?.permissions || [],
    );
    const userRoles = computed(() => page.props.auth.user?.roles || []);

    // Helper cek permission
    const can = (permission) => {
        if (!permission) return true; // Jika tidak ada permission, berarti public/umum
        if (userRoles.value.includes("super-admin")) return true; // Super admin bypass
        return userPermissions.value.includes(permission);
    };

    /**
     * Definisi Menu Utama
     * Struktur:
     * - label: Nama Menu
     * - route: Nama Route Laravel (opsional jika punya children)
     * - icon: (Opsional) SVG Path atau nama icon
     * - permission: (Opsional) 'users.view', dll.
     * - active: Logic untuk menentukan menu sedang aktif
     * - children: Array menu jika ini adalah Dropdown
     */
    const menus = computed(() => {
        const items = [
            // 1. DASHBOARD (Single Menu)
            {
                label: trans("Dashboard"),
                route: "dashboard",
                permission: null,
                active: route().current("dashboard"),
                icon: "M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6",
            },

            // 2. ADMINISTRATIVE (Dropdown Menu)
            {
                label: trans("Administrative"),
                permission: "users.view", // Hanya muncul jika punya akses user
                active: route().current("admin.users.*") || route().current("admin.roles.*"), // Specific check
                icon: "M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z",
                children: [
                    {
                        label: trans("User Management"),
                        route: "admin.users.index",
                        permission: "users.view",
                        active: route().current("admin.users.*"),
                    },
                    {
                        label: trans("Roles & Permission"),
                        route: "admin.roles.index", // Ganti nanti
                        permission: "roles.manage",
                        active: false,
                    },
                ],
            },

            // 3. CMS (Content Management System)
            {
                label: trans("CMS"),
                permission: "files.view", // Adjust permission as needed
                active:
                    route().current("admin.files.*") ||
                    route().current("admin.heroes.*") ||
                    route().current("admin.pages.*") ||
                    route().current("admin.block-templates.*"),
                icon: "M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z",
                children: [
                    {
                        label: trans("File Manager"),
                        route: "admin.files.index",
                        permission: "files.view",
                        active: route().current("admin.files.*"),
                    },
                    {
                        label: trans("Hero Manager"),
                        route: "admin.heroes.index",
                        permission: "heroes.view",
                        active: route().current("admin.heroes.*"),
                    },
                    {
                        label: trans("Pages"),
                        route: "admin.pages.index",
                        permission: "files.view",
                        active: route().current("admin.pages.*"),
                    },
                    {
                        label: trans("Block Templates"),
                        route: "admin.block-templates.index",
                        permission: "files.view",
                        active: route().current("admin.block-templates.*"),
                    },
                ],
            },

            // 4. SONGS (Song Catalog)
            {
                label: trans("Songs"),
                permission: "songs.view",
                active: route().current("admin.songs.*") || route().current("admin.song-categories.*"),
                icon: "M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z",
                children: [
                    {
                        label: trans("Song List"),
                        route: "admin.songs.index",
                        permission: "songs.view",
                        active: route().current("admin.songs.*"),
                    },
                    {
                        label: trans("Song Categories"),
                        route: "admin.song-categories.index",
                        permission: "song_categories.view",
                        active: route().current("admin.song-categories.*"),
                    },
                ],
            },

            // 5. CHURCH ADMIN (Administrasi Gereja)
            {
                label: trans("Church Administration"),
                permission: "letters.view", // Adjust permission
                active: route().current("admin.letters.*") || route().current("admin.incoming-letters.*") || route().current("admin.letter-templates.*"),
                icon: "M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253",
                children: [
                    {
                        label: trans("Congregation Management"), // Kelola Jemaat
                        route: "admin.congregations.index",
                        permission: "congregations.view",
                        active: route().current("admin.congregations.*"),
                    },
                    {
                        label: trans("Document Admin"), // Administrasi Dokumen
                        route: "admin.letters.index",
                        permission: "letters.view",
                        active: route().current("admin.letters.*"),
                    },
                    {
                        label: trans("Document Archive"), // Arsip Dokumen
                        route: "admin.documents.index",
                        permission: "documents.view",
                        active: route().current("admin.documents.*"),
                    },
                    {
                        label: trans("Templates"), // Template Surat
                        route: "admin.letter-templates.index",
                        permission: "letter_templates.view",
                        active: route().current("admin.letter-templates.*"),
                    },
                ],
            },

            // 6. SETTINGS (System Settings)
            {
                label: trans("System Settings"),
                route: "profile.show", // Contoh saja
                permission: "settings.view",
                active: route().current("profile.show"),
                icon: "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z",
            },
        ];

        // Filter menu berdasarkan permission user sebelum dikirim ke UI
        return items.filter((item) => can(item.permission));
    });

    /**
     * Definisi Menu Frontend (Public)
     * Tidak butuh permission check karena public
     */
    /**
     * Definisi Menu Frontend (Public)
     * Tidak butuh permission check karena public
     */
    const frontendMenus = computed(() => [
        { label: trans("Home"), route: "welcome", active: route().current("welcome") },
        {
            label: trans("About AB2TI"),
            route: "pages.show",
            params: { slug: "about" },
            active:
                route().current("about") ||
                (route().current("pages.show") && route().params.slug === "about"),
        },
        { label: trans("Farming Tech"), route: "sermons", active: route().current("sermons") },
        { label: trans("News"), route: "events", active: route().current("events") },
        {
            label: trans("Contact"),
            route: "pages.show",
            params: { slug: "contact" },
            active:
                route().current("contact") ||
                (route().current("pages.show") && route().params.slug === "contact"),
        },
    ]);

    return { menus, frontendMenus };
}
