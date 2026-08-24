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
    safelist: [
        { pattern: /^p-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^px-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^py-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^pt-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^pb-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^pl-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^pr-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^m-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^mx-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^my-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^mt-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^mb-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^-mt-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^gap-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^grid-cols-\d+$/, variants: ["sm", "md", "lg"] },
        { pattern: /^rounded-(sm|md|lg|xl|2xl|3xl|full)$/ },
        { pattern: /^shadow-(sm|md|lg|xl|2xl)$/ },
        { pattern: /^text-(xs|sm|base|lg|xl|2xl|3xl|4xl|5xl)$/ },
        { pattern: /^font-(normal|medium|semibold|bold|extrabold)$/ },
        "flex", "flex-col", "flex-row", "flex-wrap", "flex-1", "flex-shrink-0",
        "items-start", "items-center", "items-end",
        "justify-start", "justify-center", "justify-end", "justify-between",
        "grid", "hidden", "block", "inline-block", "inline-flex",
        "w-full", "h-full", "min-h-full",
        "overflow-hidden", "relative", "absolute",
        "border", "border-2", "border-border",
        "bg-background", "bg-surface", "bg-surface-variant",
        "text-primary", "text-text-main", "text-text-muted",
        "border-border",
        "object-cover",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                caption: ["0.8125rem", { lineHeight: "1.25rem" }],
            },
            minHeight: {
                control: "2.75rem",
            },
            colors: {
                background: withOpacity("--color-background"),
                surface: {
                    DEFAULT: withOpacity("--color-surface"),
                    variant: withOpacity("--color-surface-variant"),
                    muted: withOpacity("--color-surface-variant"),
                },
                primary: {
                    DEFAULT: withOpacity("--color-primary"),
                    dark: withOpacity("--color-primary-dark"),
                },
                secondary: withOpacity("--color-secondary"),
                accent: withOpacity("--color-accent"),
                success: withOpacity("--color-success"),
                warning: withOpacity("--color-warning"),
                danger: withOpacity("--color-danger"),
                info: withOpacity("--color-info"),
                focus: withOpacity("--color-focus"),
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
