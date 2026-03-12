<script setup>
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Array], default: '' },
    multiple: { type: Boolean, default: false },
    label: { type: String, default: 'Chọn hình ảnh' },
});

const emit = defineEmits(['update:modelValue']);

const showModal = ref(false);
const loading = ref(false);
const media = ref([]);
const folders = ref([]);
const currentFolder = ref('/');
const search = ref('');
const page = ref(1);
const hasMore = ref(false);
const selected = ref([]);

// Helpers
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Upload
const uploading = ref(false);
const uploadProgress = ref(0);

// Current value display
const currentImages = computed(() => {
    if (!props.modelValue) return [];
    if (Array.isArray(props.modelValue)) return props.modelValue;
    return [props.modelValue];
});

// Open modal
function open() {
    showModal.value = true;
    selected.value = [];
    page.value = 1;
    loadMedia();
}

// Load media via API
async function loadMedia(append = false) {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (search.value) params.set('search', search.value);
        if (currentFolder.value !== '/') params.set('folder', currentFolder.value);
        params.set('page', page.value);

        const response = await fetch(`/admin/media/browse?${params}`);
        const data = await response.json();

        if (append) {
            media.value.push(...data.data);
        } else {
            media.value = data.data;
        }
        hasMore.value = data.current_page < data.last_page;
        if (data.folders) folders.value = data.folders;
    } catch (err) {
        console.error('Failed to load media:', err);
    }
    loading.value = false;
}

// Search debounce
let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        page.value = 1;
        loadMedia();
    }, 300);
});

function setFolder(folder) {
    currentFolder.value = folder;
    page.value = 1;
    loadMedia();
}

function loadMore() {
    page.value++;
    loadMedia(true);
}

// Selection
function toggleItem(item) {
    if (props.multiple) {
        const idx = selected.value.findIndex(s => s.id === item.id);
        if (idx > -1) selected.value.splice(idx, 1);
        else selected.value.push(item);
    } else {
        selected.value = [item];
    }
}

function isItemSelected(item) {
    return selected.value.some(s => s.id === item.id);
}

// Confirm
function confirm() {
    if (props.multiple) {
        emit('update:modelValue', selected.value.map(s => s.url));
    } else if (selected.value.length) {
        emit('update:modelValue', selected.value[0].url);
    }
    showModal.value = false;
}

// Upload in picker
function triggerUpload() {
    document.getElementById('picker-file-input').click();
}

async function onUpload(e) {
    const files = Array.from(e.target.files);
    if (!files.length) return;
    e.target.value = '';

    uploading.value = true;
    const formData = new FormData();
    files.forEach(f => formData.append('files[]', f));
    formData.append('folder', currentFolder.value);

    try {
        const response = await fetch('/admin/media/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();
        const uploadedFiles = data.files || data.media || [];
        if (uploadedFiles.length) {
            media.value.unshift(...uploadedFiles);
        }
    } catch (err) {
        console.error('Upload failed:', err);
    }
    uploading.value = false;
}

// Remove image
function removeImage(url) {
    if (props.multiple) {
        const val = Array.isArray(props.modelValue) ? props.modelValue.filter(v => v !== url) : [];
        emit('update:modelValue', val);
    } else {
        emit('update:modelValue', '');
    }
}
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1.5">{{ label }}</label>

        <!-- Current selection preview -->
        <div v-if="currentImages.length" class="flex flex-wrap gap-2 mb-2">
            <div v-for="(img, idx) in currentImages" :key="idx" class="relative group w-20 h-20 rounded-lg overflow-hidden border border-gray-200">
                <img :src="img" class="w-full h-full object-cover">
                <button @click="removeImage(img)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs">&times;</button>
            </div>
        </div>

        <!-- Open Button -->
        <button @click="open" type="button" class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ currentImages.length ? 'Thay đổi' : 'Chọn từ thư viện' }}
        </button>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[70] flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[85vh] mx-4 flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Chọn hình ảnh</h3>
                        <div class="flex items-center gap-3">
                            <button @click="triggerUpload" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                Upload
                            </button>
                            <input id="picker-file-input" type="file" multiple accept="image/*" class="hidden" @change="onUpload">
                            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Search & Folders -->
                    <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-4">
                        <div class="relative flex-1">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input v-model="search" type="text" placeholder="Tìm kiếm..." class="w-full pl-10 pr-4 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="flex gap-1">
                            <button @click="setFolder('/')"
                                :class="currentFolder === '/' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-colors">
                                Tất cả
                            </button>
                            <button v-for="f in folders" :key="f" @click="setFolder(f)"
                                :class="currentFolder === f ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="px-2.5 py-1 rounded text-xs font-medium transition-colors">
                                {{ f }}
                            </button>
                        </div>
                    </div>

                    <!-- Grid -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="uploading" class="text-center py-4 text-sm text-gray-500">Đang upload...</div>

                        <div v-if="media.length" class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 gap-2">
                            <div v-for="item in media" :key="item.id"
                                @click="toggleItem(item)"
                                :class="isItemSelected(item) ? 'ring-2 ring-indigo-500 bg-indigo-50' : 'hover:bg-gray-50'"
                                class="relative rounded-lg border border-gray-200 overflow-hidden cursor-pointer aspect-square transition-all group"
                            >
                                <img :src="item.thumbnail_url || item.url" :alt="item.alt || item.name" class="w-full h-full object-cover">
                                <div v-if="isItemSelected(item)" class="absolute top-1 right-1 w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!loading" class="text-center py-12 text-gray-400">
                            <p class="text-sm">Chưa có hình ảnh nào</p>
                        </div>

                        <div v-if="loading" class="text-center py-4 text-sm text-gray-500">Đang tải...</div>

                        <div v-if="hasMore && !loading" class="text-center mt-4">
                            <button @click="loadMore" class="px-4 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 transition-colors">Tải thêm</button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-gray-200 px-6 py-3 flex items-center justify-between">
                        <span class="text-sm text-gray-500">{{ selected.length }} đã chọn</span>
                        <div class="flex gap-3">
                            <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Huỷ</button>
                            <button @click="confirm" :disabled="!selected.length"
                                class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors font-medium">
                                Chọn
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
