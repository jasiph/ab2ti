import { usePage } from "@inertiajs/vue3";

export function usePermission() {
    const page = usePage();

    /**
     * Cek apakah user memiliki salah satu permission yang diminta
     * @param {String|Array} permissions
     */
    const can = (permissions) => {
        const userPermissions = page.props.auth.user.permissions || [];
        const userRoles = page.props.auth.user.roles || [];

        // Super Admin Bypass (Frontend Side)
        if (userRoles.includes("super-admin")) {
            return true;
        }

        if (Array.isArray(permissions)) {
            return permissions.some((p) => userPermissions.includes(p));
        }

        return userPermissions.includes(permissions);
    };

    /**
     * Cek apakah user memiliki role tertentu
     * @param {String|Array} roles
     */
    const hasRole = (roles) => {
        const userRoles = page.props.auth.user.roles || [];

        if (Array.isArray(roles)) {
            return roles.some((r) => userRoles.includes(r));
        }

        return userRoles.includes(roles);
    };

    return { can, hasRole };
}
