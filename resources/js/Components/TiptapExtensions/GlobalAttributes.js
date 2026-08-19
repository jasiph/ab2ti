import { Extension } from "@tiptap/core";

export const GlobalAttributes = Extension.create({
    name: "globalAttributes",

    addGlobalAttributes() {
        return [
            {
                types: [
                    "heading",
                    "paragraph",
                    "bulletList",
                    "orderedList",
                    "listItem",
                    "blockquote",
                    "image",
                ],
                attributes: {
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
                },
            },
        ];
    },
});
