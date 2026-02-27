import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
import typography from "@tailwindcss/typography";

/** @type {import('tailwindcss').Config} */

/** Fungsi helper untuk opacity */
// function withOpacity(variableName) {
//     return ({ opacityValue }) => {
//         if (opacityValue !== undefined) {
//             return `rgba(var(${variableName}), ${opacityValue})`;
//         }
//         return `rgb(var(${variableName}))`;
//     };
// }
/** Fungsi helper untuk opacity (FIXED) */
function withOpacity(variableName) {
    return ({ opacityValue }) => {
        if (opacityValue !== undefined) {
            // GANTI INI: Gunakan sintaks slash ( / ) bukan koma
            return `rgb(var(${variableName}) / ${opacityValue})`;
        }
        return `rgb(var(${variableName}))`;
    };
}

export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./vendor/laravel/jetstream/**/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
    ],
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                // Warna Semantik (AB2TI Agricultural Theme)
                background: withOpacity("--color-background"),
                surface: {
                    DEFAULT: withOpacity("--color-surface"),
                    variant: withOpacity("--color-surface-variant"),
                },
                primary: {
                    DEFAULT: withOpacity("--color-primary"),
                    dark: withOpacity("--color-primary-dark"),
                },
                secondary: withOpacity("--color-secondary"), // Gold/Amber
                accent: withOpacity("--color-accent"),       // Red

                text: {
                    main: withOpacity("--color-text-main"),
                    muted: withOpacity("--color-text-muted"),
                },
                border: withOpacity("--color-border"),
            },
        },
    },
    plugins: [forms, typography],
};
