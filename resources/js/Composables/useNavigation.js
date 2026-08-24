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
        if (!permission) return true;
        if (userRoles.value.includes("super-admin")) return true;
        if (Array.isArray(permission)) {
            return permission.some((p) => userPermissions.value.includes(p));
        }
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
                permission: ["users.view", "roles.manage"],
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
                permission: ["cms.view", "media.view", "heroes.view"],
                active:
                    route().current("admin.files.*") ||
                    route().current("admin.heroes.*") ||
                    route().current("admin.pages.*") ||
                    route().current("admin.block-templates.*") ||
                    route().current("admin.welcome-section.*"),
                icon: "M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z",
                children: [
                    {
                        label: trans("File Manager"),
                        route: "admin.files.index",
                        permission: "media.view",
                        active: route().current("admin.files.*"),
                    },
                    {
                        label: trans("Hero Manager"),
                        route: "admin.heroes.index",
                        permission: "heroes.view",
                        active: route().current("admin.heroes.*"),
                    },
                    {
                        label: trans("Welcome Section"),
                        route: "admin.welcome-section.edit",
                        permission: "cms.view",
                        active: route().current("admin.welcome-section.*"),
                    },
                    {
                        label: trans("Pages"),
                        route: "admin.pages.index",
                        permission: "cms.view",
                        active: route().current("admin.pages.*"),
                    },
                    {
                        label: trans("Block Templates"),
                        route: "admin.block-templates.index",
                        permission: "cms.view",
                        active: route().current("admin.block-templates.*"),
                    },
                ],
            },

            {
                label: trans("Publishing"),
                permission: ["publishing-publications.view"],
                active: route().current("admin.publishing.*"),
                icon: "M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z",
                children: [
                    {
                        label: trans("Publications"),
                        route: "admin.publishing.publications.index",
                        permission: "publishing-publications.view",
                        active: route().current("admin.publishing.publications.*"),
                    },
                ],
            },

            {
                label: trans("Knowledge"),
                permission: ["knowledge-articles.view"],
                active: route().current("admin.knowledge.*"),
                icon: "M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253",
                children: [
                    {
                        label: trans("Knowledge articles"),
                        route: "admin.knowledge.articles.index",
                        permission: "knowledge-articles.view",
                        active: route().current("admin.knowledge.articles.*"),
                    },
                ],
            },

            {
                label: trans("Membership"),
                permission: [
                    "membership-applications.view",
                    "membership-members.view",
                ],
                active: route().current("admin.membership.*"),
                icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
                children: [
                    {
                        label: trans("Membership applications"),
                        route: "admin.membership.applications.index",
                        permission: "membership-applications.view",
                        active: route().current("admin.membership.applications.*"),
                    },
                    {
                        label: trans("AB2TI members"),
                        route: "admin.membership.members.index",
                        permission: "membership-members.view",
                        active: route().current("admin.membership.members.*"),
                    },
                ],
            },

            {
                label: trans("Lapak Anggota"),
                permission: ["lapak.view"],
                active: route().current("admin.lapak.*"),
                icon: "M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z",
                children: [
                    {
                        label: trans("Lapak storefronts"),
                        route: "admin.lapak.storefronts.index",
                        permission: "lapak.view",
                        active: route().current("admin.lapak.storefronts.*"),
                    },
                    {
                        label: trans("Lapak listings"),
                        route: "admin.lapak.listings.index",
                        permission: "lapak.view",
                        active: route().current("admin.lapak.listings.*"),
                    },
                    {
                        label: trans("Lapak categories"),
                        route: "admin.lapak.categories.index",
                        permission: "lapak.view",
                        active: route().current("admin.lapak.categories.*"),
                    },
                ],
            },

            // 4. SETTINGS (System Settings)
            {
                label: trans("System Settings"),
                route: "profile.show", // Contoh saja
                permission: null,
                active: route().current("profile.show"),
                icon: "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z",
            },
        ];

        // Filter menu berdasarkan permission user sebelum dikirim ke UI
        return items
            .map((item) => {
                if (!item.children) {
                    return item;
                }

                return {
                    ...item,
                    children: item.children.filter((child) => can(child.permission)),
                };
            })
            .filter((item) => {
                if (item.children) {
                    return item.children.length > 0;
                }

                return can(item.permission);
            });
    });

    /**
     * Public destinations currently in RC. Do not add placeholder or
     * coming-soon items.
     */
    const frontendMenus = computed(() => [
        { label: "Home", route: "welcome", active: route().current("welcome") },
        {
            label: "About AB2TI",
            route: "about",
            active:
                route().current("about") ||
                (route().current("pages.show") && route().params.slug === "about"),
        },
        {
            label: "Publications",
            route: "publications.index",
            active: route().current("publications.*"),
        },
        {
            label: "Teknologi Tani",
            route: "knowledge.index",
            active: route().current("knowledge.*"),
        },
        {
            label: "Lapak Anggota",
            route: "lapak.index",
            active: route().current("lapak.*"),
        },
        {
            label: "Membership",
            route: "membership.index",
            active: route().current("membership.*"),
        },
        {
            label: "Contact",
            route: "contact",
            active:
                route().current("contact") ||
                (route().current("pages.show") && route().params.slug === "contact"),
        },
    ]);

    return { menus, frontendMenus };
}
