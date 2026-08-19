import { Node, mergeAttributes } from "@tiptap/core";

export const GlobalDiv = Node.create({
    name: "div",
    content: "block*",
    group: "block",

    addOptions() {
        return {
            HTMLAttributes: {},
        };
    },

    parseHTML() {
        return [
            {
                tag: "div",
                getAttrs: (node) => {
                    if (node.classList && node.classList.contains("tiptap-block-wrapper")) return false;
                    if (node.classList && node.classList.contains("tiptap-block-content")) return false;
                    return null;
                },
            },
        ];
    },

    addAttributes() {
        return {
            class: {
                default: null,
                parseHTML: (element) => element.getAttribute("class"),
                renderHTML: (attributes) => {
                    if (!attributes.class) return {};
                    return { class: attributes.class };
                },
            },
            style: {
                default: null,
                parseHTML: (element) => element.getAttribute("style"),
                renderHTML: (attributes) => {
                    if (!attributes.style) return {};
                    return { style: attributes.style };
                },
            },
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute("id"),
                renderHTML: (attributes) => {
                    if (!attributes.id) return {};
                    return { id: attributes.id };
                },
            },
        };
    },

    renderHTML({ HTMLAttributes }) {
        return ["div", mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0];
    },
});
