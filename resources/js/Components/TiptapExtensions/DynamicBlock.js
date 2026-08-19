import { Node, mergeAttributes } from "@tiptap/core";

export const DynamicBlock = Node.create({
    name: "dynamicBlock",
    group: "block",
    content: "block*",
    defining: true,
    isolating: true,

    addAttributes() {
        return {
            blockType: {
                default: "Block Content",
                parseHTML: (element) => element.getAttribute("data-block-type"),
                renderHTML: (attributes) => {
                    return {
                        "data-block-type": attributes.blockType,
                    };
                },
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: "div.tiptap-block-wrapper",
                contentElement: ".tiptap-block-content",
            },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            "div",
            mergeAttributes(HTMLAttributes, { class: "tiptap-block-wrapper" }),
            ["div", { class: "tiptap-block-content" }, 0],
        ];
    },
});
