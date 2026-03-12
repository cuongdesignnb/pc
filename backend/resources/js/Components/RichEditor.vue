<script setup>
import { ref, watch } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import Color from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import { Table } from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import Placeholder from '@tiptap/extension-placeholder';
import Youtube from '@tiptap/extension-youtube';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Nhập nội dung...' },
    minHeight: { type: String, default: '200px' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: { openOnClick: false, HTMLAttributes: { class: 'text-indigo-600 underline' } },
        }),
        Image.configure({ inline: false, allowBase64: true }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Highlight.configure({ multicolor: true }),
        Color,
        TextStyle,
        Table.configure({ resizable: true }),
        TableRow,
        TableCell,
        TableHeader,
        Placeholder.configure({ placeholder: props.placeholder }),
        Youtube.configure({ inline: false, ccLanguage: 'vi' }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none px-4 py-3',
            style: `min-height: ${props.minHeight}`,
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val || '', false);
    }
});

function setLink() {
    const url = window.prompt('Nhập URL:', editor.value.getAttributes('link').href || 'https://');
    if (url === null) return;
    if (url === '') { editor.value.chain().focus().extendMarkRange('link').unsetLink().run(); return; }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function addYoutube() {
    const url = window.prompt('Nhập URL YouTube:');
    if (url) editor.value.commands.setYoutubeVideo({ src: url, width: 640, height: 360 });
}

function insertTable() {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

// ─── Helpers ───────────────────────────────────────────────────────────
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// ─── Media Library Integration ─────────────────────────────────────────
const showMediaModal = ref(false);
const mediaLoading = ref(false);
const mediaItems = ref([]);
const mediaFolders = ref([]);
const mediaFolder = ref('/');
const mediaSearch = ref('');
const mediaPage = ref(1);
const mediaHasMore = ref(false);
const mediaSelected = ref([]);
const mediaUploading = ref(false);
const mediaInsertMode = ref('single'); // 'single' or 'url'

function openMediaLibrary() {
    showMediaModal.value = true;
    mediaSelected.value = [];
    mediaPage.value = 1;
    mediaInsertMode.value = 'single';
    loadMediaItems();
}

async function loadMediaItems(append = false) {
    mediaLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (mediaSearch.value) params.set('search', mediaSearch.value);
        if (mediaFolder.value !== '/') params.set('folder', mediaFolder.value);
        params.set('page', mediaPage.value);
        params.set('type', 'image');

        const res = await fetch(`/admin/media/browse?${params}`);
        const data = await res.json();

        if (append) {
            mediaItems.value.push(...data.data);
        } else {
            mediaItems.value = data.data;
        }
        mediaHasMore.value = data.current_page < data.last_page;
        if (data.folders) mediaFolders.value = data.folders;
    } catch (err) {
        console.error('Failed to load media:', err);
    }
    mediaLoading.value = false;
}

let mediaSearchTimer = null;
watch(mediaSearch, () => {
    clearTimeout(mediaSearchTimer);
    mediaSearchTimer = setTimeout(() => {
        mediaPage.value = 1;
        loadMediaItems();
    }, 300);
});

function setMediaFolder(folder) {
    mediaFolder.value = folder;
    mediaPage.value = 1;
    loadMediaItems();
}

function loadMoreMedia() {
    mediaPage.value++;
    loadMediaItems(true);
}

function toggleMediaItem(item) {
    const idx = mediaSelected.value.findIndex(s => s.id === item.id);
    if (idx > -1) mediaSelected.value.splice(idx, 1);
    else mediaSelected.value.push(item);
}

function isMediaSelected(item) {
    return mediaSelected.value.some(s => s.id === item.id);
}

function confirmMediaInsert() {
    mediaSelected.value.forEach(item => {
        editor.value.chain().focus().setImage({
            src: item.url,
            alt: item.alt || item.name,
        }).run();
    });
    showMediaModal.value = false;
}

function triggerMediaUpload() {
    document.getElementById('editor-media-upload').click();
}

async function onMediaUpload(e) {
    const files = Array.from(e.target.files);
    if (!files.length) return;
    e.target.value = '';

    mediaUploading.value = true;
    const formData = new FormData();
    files.forEach(f => formData.append('files[]', f));
    formData.append('folder', mediaFolder.value);

    try {
        const res = await fetch('/admin/media/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await res.json();
        const uploadedFiles = data.files || data.media || [];
        if (uploadedFiles.length) {
            mediaItems.value.unshift(...uploadedFiles);
        }
    } catch (err) {
        console.error('Upload failed:', err);
    }
    mediaUploading.value = false;
}

function insertImageByUrl() {
    const url = window.prompt('Nhập URL hình ảnh:');
    if (url) editor.value.chain().focus().setImage({ src: url }).run();
}
</script>

<template>
<div class="rich-editor border border-gray-300 rounded-lg overflow-hidden bg-white">
    <!-- Toolbar -->
    <div v-if="editor" class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-gray-200 bg-gray-50">
        <!-- Text style -->
        <div class="flex items-center gap-0.5 pr-2 border-r border-gray-200">
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="editor.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="px-1.5 py-1 rounded text-xs font-bold" title="Heading 2">H2</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="editor.isActive('heading', { level: 3 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="px-1.5 py-1 rounded text-xs font-bold" title="Heading 3">H3</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 4 }).run()" :class="editor.isActive('heading', { level: 4 }) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="px-1.5 py-1 rounded text-xs font-bold" title="Heading 4">H4</button>
            <button type="button" @click="editor.chain().focus().setParagraph().run()" :class="editor.isActive('paragraph') && !editor.isActive('heading') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="px-1.5 py-1 rounded text-xs" title="Paragraph">P</button>
        </div>

        <!-- Format -->
        <div class="flex items-center gap-0.5 px-2 border-r border-gray-200">
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="editor.isActive('bold') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="In đậm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="editor.isActive('italic') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="In nghiêng">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0l-4 16m-2 0h4"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="editor.isActive('underline') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Gạch chân">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3M4 21h16"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="editor.isActive('strike') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Gạch ngang">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4c-1.5-1-3.5-1.5-5-1-2 .7-3 2.5-2.5 4.5C9 9.5 11 10.5 14 11.5M4 12h16M8 20c1.5 1 3.5 1.5 5 1 2-.7 3-2.5 2.5-4.5-.3-1-1-1.8-2-2.5"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleHighlight().run()" :class="editor.isActive('highlight') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Highlight">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M15.243 3.515a2 2 0 012.828 0l2.414 2.414a2 2 0 010 2.828l-9.9 9.9a2 2 0 01-1.414.586H5.757a1 1 0 01-1-1v-3.414a2 2 0 01.586-1.414l9.9-9.9zM3 21h18v-2H3v2z"/></svg>
            </button>
        </div>

        <!-- Alignment -->
        <div class="flex items-center gap-0.5 px-2 border-r border-gray-200">
            <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="editor.isActive({textAlign:'left'}) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Căn trái">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M3 6h18M3 10h12M3 14h18M3 18h12"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="editor.isActive({textAlign:'center'}) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Căn giữa">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M3 6h18M6 10h12M3 14h18M6 18h12"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="editor.isActive({textAlign:'right'}) ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Căn phải">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M3 6h18M9 10h12M3 14h18M9 18h12"/></svg>
            </button>
        </div>

        <!-- Lists -->
        <div class="flex items-center gap-0.5 px-2 border-r border-gray-200">
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="editor.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Danh sách">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h.01M4 12h.01M4 18h.01M8 6h12M8 12h12M8 18h12"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="editor.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Danh sách đánh số">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h.01M4 12h.01M4 18h.01M8 6h12M8 12h12M8 18h12"/><text x="2" y="8" font-size="6" fill="currentColor">1</text></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="editor.isActive('blockquote') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Trích dẫn">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z"/></svg>
            </button>
        </div>

        <!-- Insert -->
        <div class="flex items-center gap-0.5 px-2 border-r border-gray-200">
            <button type="button" @click="setLink" :class="editor.isActive('link') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Chèn link">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            </button>
            <button type="button" @click="openMediaLibrary" class="p-1 rounded text-gray-600 hover:bg-gray-200" title="Chèn ảnh từ thư viện">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </button>
            <button type="button" @click="insertImageByUrl" class="p-1 rounded text-gray-600 hover:bg-gray-200" title="Chèn ảnh bằng URL">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/><circle cx="18" cy="6" r="3" stroke-width="1.5"/></svg>
            </button>
            <button type="button" @click="addYoutube" class="p-1 rounded text-gray-600 hover:bg-gray-200" title="Chèn YouTube">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </button>
            <button type="button" @click="insertTable" class="p-1 rounded text-gray-600 hover:bg-gray-200" title="Chèn bảng">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M14 3v18M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"/></svg>
            </button>
        </div>

        <!-- Block -->
        <div class="flex items-center gap-0.5 px-2 border-r border-gray-200">
            <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="editor.isActive('codeBlock') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-200'" class="p-1 rounded" title="Code block">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" class="p-1 rounded text-gray-600 hover:bg-gray-200" title="Đường kẻ ngang">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M3 12h18"/></svg>
            </button>
        </div>

        <!-- Undo/Redo -->
        <div class="flex items-center gap-0.5 pl-2">
            <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-1 rounded text-gray-600 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed" title="Hoàn tác">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a4 4 0 014 4v0a4 4 0 01-4 4H3m0-8l4-4m-4 4l4 4"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="p-1 rounded text-gray-600 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed" title="Làm lại">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a4 4 0 00-4 4v0a4 4 0 004 4h10m0-8l-4-4m4 4l-4 4"/></svg>
            </button>
        </div>
    </div>

    <!-- Editor content -->
    <EditorContent :editor="editor" />

    <!-- Media Library Modal -->
    <Teleport to="body">
        <div v-if="showMediaModal" class="fixed inset-0 z-[80] flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showMediaModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[85vh] mx-4 flex flex-col overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-white">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <h3 class="text-lg font-semibold text-gray-900">Chọn ảnh từ thư viện</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="triggerMediaUpload" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            Upload ảnh
                        </button>
                        <input id="editor-media-upload" type="file" multiple accept="image/*" class="hidden" @change="onMediaUpload">
                        <button @click="showMediaModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Search & Folders -->
                <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-4">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="mediaSearch" type="text" placeholder="Tìm ảnh..." class="w-full pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <button @click="setMediaFolder('/')"
                            :class="mediaFolder === '/' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 rounded text-xs font-medium transition-colors">
                            Tất cả
                        </button>
                        <button v-for="f in mediaFolders" :key="f" @click="setMediaFolder(f)"
                            :class="mediaFolder === f ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 rounded text-xs font-medium transition-colors">
                            {{ f }}
                        </button>
                    </div>
                </div>

                <!-- Grid -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="mediaUploading" class="flex items-center justify-center gap-2 py-4 text-sm text-indigo-600">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Đang upload...
                    </div>

                    <div v-if="mediaItems.length" class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 gap-2">
                        <div v-for="item in mediaItems" :key="item.id"
                            @click="toggleMediaItem(item)"
                            :class="isMediaSelected(item) ? 'ring-2 ring-indigo-500 bg-indigo-50 scale-95' : 'hover:ring-2 hover:ring-gray-300'"
                            class="relative rounded-lg border border-gray-200 overflow-hidden cursor-pointer aspect-square transition-all group"
                        >
                            <img :src="item.thumbnail_url || item.url" :alt="item.alt || item.name" class="w-full h-full object-cover">
                            <div v-if="isMediaSelected(item)" class="absolute top-1 right-1 w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center shadow-sm">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <p class="text-white text-[10px] truncate">{{ item.name }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!mediaLoading" class="text-center py-16 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm">Chưa có hình ảnh nào</p>
                        <button @click="triggerMediaUpload" class="mt-2 text-indigo-600 hover:text-indigo-800 text-sm font-medium">Upload ảnh đầu tiên</button>
                    </div>

                    <div v-if="mediaLoading" class="flex items-center justify-center gap-2 py-4 text-sm text-gray-500">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Đang tải...
                    </div>

                    <div v-if="mediaHasMore && !mediaLoading" class="text-center mt-4">
                        <button @click="loadMoreMedia" class="px-4 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 transition-colors">Tải thêm</button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-200 px-6 py-3 flex items-center justify-between bg-gray-50">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">
                            <template v-if="mediaSelected.length">
                                <span class="font-medium text-indigo-600">{{ mediaSelected.length }}</span> ảnh đã chọn
                            </template>
                            <template v-else>Chọn ảnh để chèn vào nội dung</template>
                        </span>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showMediaModal = false" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-200 rounded-lg transition-colors">Hủy</button>
                        <button @click="confirmMediaInsert" :disabled="!mediaSelected.length"
                            class="px-5 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium">
                            Chèn {{ mediaSelected.length ? `(${mediaSelected.length})` : '' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</div>
</template>

<style>
/* TipTap editor styles */
.rich-editor .tiptap {
    outline: none;
}
.rich-editor .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
}

/* Prose-like styles for the editor content */
.rich-editor .tiptap h2 { font-size: 1.5em; font-weight: 700; margin-top: 1em; margin-bottom: 0.5em; line-height: 1.3; color: #111827; }
.rich-editor .tiptap h3 { font-size: 1.25em; font-weight: 600; margin-top: 0.8em; margin-bottom: 0.4em; line-height: 1.35; color: #111827; }
.rich-editor .tiptap h4 { font-size: 1.1em; font-weight: 600; margin-top: 0.6em; margin-bottom: 0.3em; color: #374151; }
.rich-editor .tiptap p { margin-bottom: 0.5em; line-height: 1.6; }
.rich-editor .tiptap ul { list-style: disc; padding-left: 1.5em; margin-bottom: 0.5em; }
.rich-editor .tiptap ol { list-style: decimal; padding-left: 1.5em; margin-bottom: 0.5em; }
.rich-editor .tiptap li { margin-bottom: 0.2em; }
.rich-editor .tiptap blockquote { border-left: 3px solid #6366f1; padding-left: 1em; margin: 0.5em 0; color: #4b5563; font-style: italic; }
.rich-editor .tiptap code { background: #f3f4f6; padding: 0.15em 0.3em; border-radius: 4px; font-size: 0.9em; color: #dc2626; }
.rich-editor .tiptap pre { background: #1f2937; color: #e5e7eb; padding: 1em; border-radius: 8px; overflow-x: auto; margin: 0.5em 0; }
.rich-editor .tiptap pre code { background: none; color: inherit; padding: 0; }
.rich-editor .tiptap a { color: #4f46e5; text-decoration: underline; cursor: pointer; }
.rich-editor .tiptap img { max-width: 100%; border-radius: 8px; margin: 0.5em 0; }
.rich-editor .tiptap hr { border: none; border-top: 2px solid #e5e7eb; margin: 1em 0; }
.rich-editor .tiptap mark { background-color: #fef08a; padding: 0.1em 0.2em; border-radius: 2px; }

/* Table styles */
.rich-editor .tiptap table { border-collapse: collapse; width: 100%; margin: 0.5em 0; }
.rich-editor .tiptap td, .rich-editor .tiptap th { border: 1px solid #d1d5db; padding: 0.5em 0.75em; text-align: left; }
.rich-editor .tiptap th { background: #f9fafb; font-weight: 600; }
.rich-editor .tiptap tr:hover td { background: #f9fafb; }

/* YouTube embed */
.rich-editor .tiptap div[data-youtube-video] { margin: 0.5em 0; }
.rich-editor .tiptap div[data-youtube-video] iframe { border-radius: 8px; max-width: 100%; }

/* Selected node styling */
.rich-editor .tiptap .ProseMirror-selectednode { outline: 2px solid #6366f1; outline-offset: 2px; border-radius: 4px; }
</style>
