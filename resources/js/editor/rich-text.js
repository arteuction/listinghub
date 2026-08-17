import { Editor } from '@tiptap/core'
import Document from '@tiptap/extension-document'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import Heading from '@tiptap/extension-heading'
import Bold from '@tiptap/extension-bold'
import Italic from '@tiptap/extension-italic'
import Strike from '@tiptap/extension-strike'
import Blockquote from '@tiptap/extension-blockquote'
import BulletList from '@tiptap/extension-bullet-list'
import OrderedList from '@tiptap/extension-ordered-list'
import ListItem from '@tiptap/extension-list-item'
import HardBreak from '@tiptap/extension-hard-break'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import History from '@tiptap/extension-history'

/**
 * Alpine.js data factory for the rich-text editor.
 *
 * Usage in a Blade view:
 *   <div x-data="richTextEditor({ hiddenInputId: 'content_tiptap', initialJson: @json($doc) })">
 */
export function richTextEditor({ hiddenInputId, initialJson = null, placeholder = '' }) {
    return {
        editor: null,
        isFocused: false,

        init() {
            const hiddenInput = document.getElementById(hiddenInputId)

            this.editor = new Editor({
                element: this.$refs.editorContent,
                extensions: [
                    Document,
                    Paragraph,
                    Text,
                    Heading.configure({ levels: [2, 3] }),
                    Bold,
                    Italic,
                    Strike,
                    Blockquote,
                    BulletList,
                    OrderedList,
                    ListItem,
                    HardBreak,
                    Link.configure({
                        openOnClick: false,
                        HTMLAttributes: {
                            rel: 'noopener noreferrer nofollow',
                            target: null,
                        },
                        validate: href => /^(https?:\/\/|\/)/.test(href),
                    }),
                    Placeholder.configure({ placeholder }),
                    History,
                ],
                content: initialJson,
                onFocus: () => { this.isFocused = true },
                onBlur: () => { this.isFocused = false },
                onUpdate: ({ editor }) => {
                    if (hiddenInput) {
                        hiddenInput.value = JSON.stringify(editor.getJSON())
                    }
                },
                editorProps: {
                    attributes: {
                        class: 'prose prose-sm max-w-none focus:outline-none min-h-[12rem] px-4 py-3',
                        'aria-label': placeholder || 'Rich text editor',
                        role: 'textbox',
                        'aria-multiline': 'true',
                    },
                },
            })

            // Seed the hidden input with the initial doc on mount
            if (hiddenInput && this.editor) {
                hiddenInput.value = JSON.stringify(this.editor.getJSON())
            }
        },

        destroy() {
            this.editor?.destroy()
        },

        // Toolbar command helpers called from x-on:click
        toggleBold()      { this.editor?.chain().focus().toggleBold().run() },
        toggleItalic()    { this.editor?.chain().focus().toggleItalic().run() },
        toggleStrike()    { this.editor?.chain().focus().toggleStrike().run() },
        toggleBlockquote(){ this.editor?.chain().focus().toggleBlockquote().run() },
        toggleBulletList(){ this.editor?.chain().focus().toggleBulletList().run() },
        toggleOrderedList(){ this.editor?.chain().focus().toggleOrderedList().run() },
        undo()            { this.editor?.chain().focus().undo().run() },
        redo()            { this.editor?.chain().focus().redo().run() },

        setHeading(level) {
            this.editor?.chain().focus().toggleHeading({ level }).run()
        },

        setLink() {
            const existing = this.editor?.getAttributes('link').href ?? ''
            const href = window.prompt('URL (https:// or /path):', existing)
            if (href === null) return
            if (href === '') {
                this.editor?.chain().focus().unsetLink().run()
                return
            }
            if (!/^(https?:\/\/|\/)/.test(href)) {
                window.alert('Only https:// links or relative paths starting with / are allowed.')
                return
            }
            this.editor?.chain().focus().setLink({ href }).run()
        },

        // Reactive state helpers for toolbar active states
        isActive(name, attrs = {}) {
            return this.editor?.isActive(name, attrs) ?? false
        },

        canUndo() { return this.editor?.can().undo() ?? false },
        canRedo() { return this.editor?.can().redo() ?? false },
    }
}
