<script setup>
import { ref, onBeforeUnmount, watch } from "vue";
import { useEditor, EditorContent } from "@tiptap/vue-3";
import { BubbleMenu } from "@tiptap/vue-3/menus";
import StarterKit from "@tiptap/starter-kit";
import Image from "@tiptap/extension-image";
import { DynamicBlock } from "./TiptapExtensions/DynamicBlock";
import { GlobalDiv } from "./TiptapExtensions/GlobalDiv";
import { GlobalAttributes } from "./TiptapExtensions/GlobalAttributes";
import MediaPickerModal from "@/Components/MediaPickerModal.vue";
import BlockTemplatePickerModal from "@/Components/BlockTemplatePickerModal.vue";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["update:modelValue"]);

const isCodeView = ref(false);
const codeViewContent = ref("");
const showMediaPicker = ref(false);
const showBlockPicker = ref(false);

const editor = useEditor({
    content: props.modelValue || "",
    extensions: [
        StarterKit.configure({
            link: { openOnClick: false },
        }),
        Image.configure({ inline: false }),
        GlobalDiv,
        GlobalAttributes,
        DynamicBlock,
    ],
    editorProps: {
        attributes: {
            class: "prose prose-sm sm:prose-base max-w-none focus:outline-none min-h-[400px] p-4 dark:prose-invert dark:text-gray-100",
        },
    },
    onUpdate: ({ editor }) => {
        if (!isCodeView.value) {
            emit("update:modelValue", editor.getHTML());
        }
    },
});

const toggleCodeView = () => {
    isCodeView.value = !isCodeView.value;
    if (isCodeView.value) {
        codeViewContent.value = editor.value ? editor.value.getHTML() : props.modelValue;
    } else if (editor.value) {
        editor.value.commands.setContent(codeViewContent.value, { emitUpdate: false });
    }
};

const handleHtmlInput = (e) => {
    codeViewContent.value = e.target.value;
    emit("update:modelValue", e.target.value);
};

const insertImage = (media) => {
    const items = Array.isArray(media) ? media : [media];

    if (editor.value && items.length) {
        const url = items[0].url || items[0].original_url;
        const imageHtml = `<div class="p-0 h-full w-auto overflow-hidden rounded-xl"><img src="${url}" class="p-0 m-0 w-auto h-full object-cover" /></div><p></p>`;
        editor.value.chain().focus().insertContent(imageHtml).run();
    }
};

const insertBlock = (block) => {
    if (editor.value) {
        const minifiedHtml = String(block.html_content || "").replace(/>\s+</g, "><").trim();
        const type = String(block.name || "Block Content").replace(/["<>]/g, "");

        const wrappedContent = `
            <div class="tiptap-block-wrapper" data-block-type="${type}">
                <div class="tiptap-block-content">${minifiedHtml}</div>
            </div>
            <p></p>
        `;
        editor.value.chain().focus().insertContent(wrappedContent).run();
    }
};

watch(
    () => props.modelValue,
    (newValue) => {
        if (
            editor.value &&
            !isCodeView.value &&
            editor.value.getHTML() !== newValue
        ) {
            editor.value.commands.setContent(newValue, { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div
        class="border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900 shadow-sm flex flex-col transition-colors duration-200"
    >
        <div
            v-if="editor"
            class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-2 flex flex-wrap gap-1 items-center transition-colors duration-200"
        >
            <div
                class="flex gap-1"
                :class="{ 'opacity-50 pointer-events-none': isCodeView }"
            >
                <button
                    @click="editor.chain().focus().toggleBold().run()"
                    class="p-2 rounded text-sm font-bold transition-colors"
                    :class="
                        editor.isActive('bold')
                            ? 'bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'hover:bg-gray-200 text-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    title="Bold"
                    type="button"
                >
                    B
                </button>

                <button
                    @click="editor.chain().focus().toggleItalic().run()"
                    class="p-2 rounded text-sm italic transition-colors"
                    :class="
                        editor.isActive('italic')
                            ? 'bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'hover:bg-gray-200 text-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    title="Italic"
                    type="button"
                >
                    I
                </button>

                <div
                    class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1 self-center"
                ></div>

                <button
                    @click="
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    "
                    class="p-2 rounded text-sm font-bold transition-colors"
                    :class="
                        editor.isActive('heading', { level: 2 })
                            ? 'bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'hover:bg-gray-200 text-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    type="button"
                >
                    H2
                </button>

                <button
                    @click="
                        editor.chain().focus().toggleHeading({ level: 3 }).run()
                    "
                    class="p-2 rounded text-xs font-bold transition-colors"
                    :class="
                        editor.isActive('heading', { level: 3 })
                            ? 'bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'hover:bg-gray-200 text-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    type="button"
                >
                    H3
                </button>

                <div
                    class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1 self-center"
                ></div>

                <button
                    @click="editor.chain().focus().toggleBulletList().run()"
                    class="p-2 rounded text-sm transition-colors"
                    :class="
                        editor.isActive('bulletList')
                            ? 'bg-gray-300 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'hover:bg-gray-200 text-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    type="button"
                >
                    List
                </button>

                <button
                    @click="showMediaPicker = true"
                    type="button"
                    class="flex items-center gap-1 px-3 py-1 bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900 text-sm font-medium transition-colors"
                >
                    📷 Image
                </button>
                <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1 self-center"></div>
                <button
                    @click="showBlockPicker = true"
                    type="button"
                    class="flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-600 dark:bg-amber-900/50 dark:text-amber-300 rounded hover:bg-amber-100 dark:hover:bg-amber-900 text-sm font-medium transition-colors"
                    title="Sisipkan Dynamic Block Template"
                >
                    ✨ Blocks
                </button>
            </div>

            <div class="flex-grow"></div>

            <button
                type="button"
                @click="toggleCodeView"
                :class="
                    isCodeView
                        ? 'bg-gray-800 text-white dark:bg-black dark:border-gray-600'
                        : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                "
                class="flex items-center gap-1 px-3 py-1 rounded text-xs font-mono border border-transparent transition-colors"
            >
                <span v-if="!isCodeView">&lt;/&gt; Code</span>
                <span v-else>👁 Preview</span>
            </button>
        </div>

        <bubble-menu
            v-if="editor"
            :editor="editor"
            :tippy-options="{ duration: 100 }"
            class="flex items-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg rounded-lg overflow-hidden p-1 gap-1"
        >
            <button
                @click="editor.chain().focus().toggleBold().run()"
                :class="editor.isActive('bold') ? 'bg-gray-200 dark:bg-gray-600' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                class="px-3 py-1.5 rounded font-bold text-gray-800 dark:text-gray-200 text-sm transition"
            >
                B
            </button>
            <button
                @click="editor.chain().focus().toggleItalic().run()"
                :class="editor.isActive('italic') ? 'bg-gray-200 dark:bg-gray-600' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                class="px-3 py-1.5 rounded italic text-gray-800 dark:text-gray-200 text-sm transition"
            >
                I
            </button>
            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
            <button
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="editor.isActive('heading', { level: 2 }) ? 'bg-gray-200 dark:bg-gray-600' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                class="px-3 py-1.5 rounded font-bold text-gray-800 dark:text-gray-200 text-sm transition"
            >
                H2
            </button>
            <button
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="editor.isActive('heading', { level: 3 }) ? 'bg-gray-200 dark:bg-gray-600' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                class="px-3 py-1.5 rounded font-bold text-gray-800 dark:text-gray-200 text-sm transition"
            >
                H3
            </button>
        </bubble-menu>

        <div class="relative flex-1 bg-white dark:bg-gray-900">
            <editor-content v-show="!isCodeView" :editor="editor" />

            <textarea
                v-show="isCodeView"
                :value="codeViewContent"
                @input="handleHtmlInput"
                class="w-full h-full min-h-[400px] p-4 font-mono text-sm bg-gray-900 text-green-400 focus:outline-none resize-y border-none"
                placeholder="Write HTML here..."
            ></textarea>
        </div>

        <MediaPickerModal
            :show="showMediaPicker"
            @close="showMediaPicker = false"
            @select="insertImage"
        />

        <BlockTemplatePickerModal
            :show="showBlockPicker"
            @close="showBlockPicker = false"
            @select="insertBlock"
        />
    </div>
</template>

<style>
.ProseMirror {
    min-height: 400px;
    outline: none;
}

.prose ul {
    list-style-type: disc;
    padding-left: 1.5em;
}
.prose ol {
    list-style-type: decimal;
    padding-left: 1.5em;
}
</style>
