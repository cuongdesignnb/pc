<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// Controlled rename of a published (locked) slug: preview first, then change.
// The backend writes a 301 from the old URL in the same transaction.
const props = defineProps({
    entityType: { type: String, required: true },
    entityId: { type: Number, required: true },
    currentSlug: { type: String, required: true },
});

const newSlug = ref(props.currentSlug);
const reason = ref('');
const preview = ref(null);
const error = ref('');
const busy = ref(false);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function firstError(data, fallback) {
    const errors = data?.errors ? Object.values(data.errors).flat() : [];
    return errors[0] || data?.message || fallback;
}

async function call(method, path, body) {
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(firstError(data, 'Không thể đổi slug.'));
    return data;
}

const base = () => `/admin/seo/slugs/${props.entityType}/${props.entityId}`;

async function runPreview() {
    busy.value = true;
    error.value = '';
    preview.value = null;
    try {
        preview.value = await call('GET', `${base()}/preview?new_slug=${encodeURIComponent(newSlug.value.trim())}`);
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function runChange() {
    if (!preview.value) return;
    busy.value = true;
    error.value = '';
    try {
        await call('POST', `${base()}/change`, { new_slug: preview.value.new_slug, reason: reason.value.trim() });
        router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="bg-slate-900 rounded-lg border border-amber-500/30 p-6 space-y-3">
        <div>
            <h4 class="text-sm font-semibold text-amber-300">Đổi slug đã công khai</h4>
            <p class="text-xs text-slate-400 mt-1">Slug hiện tại: <code class="text-slate-200">{{ currentSlug }}</code>. Đổi ở đây sẽ tự tạo chuyển hướng 301 từ link cũ sang link mới.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <input v-model="newSlug" placeholder="slug-moi" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" @input="preview = null" />
            <input v-model="reason" placeholder="Lý do (bắt buộc), vd: sửa slug bị lặp" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div v-if="preview" class="text-xs text-slate-300 space-y-1">
            <p>Link cũ: <span class="text-slate-400">{{ preview.old_url }}</span></p>
            <p>Link mới: <span class="text-emerald-400">{{ preview.new_url }}</span></p>
            <p v-if="preview.will_redirect">Link cũ sẽ chuyển hướng 301 sang link mới ({{ preview.affected_url_count }} URL bị ảnh hưởng).</p>
        </div>
        <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
        <div class="flex gap-2 justify-end">
            <button type="button" :disabled="busy || !newSlug.trim() || newSlug.trim() === currentSlug" class="px-4 py-2 text-sm rounded-lg border border-slate-600 text-slate-200 hover:bg-slate-800 disabled:opacity-50" @click="runPreview">Xem trước</button>
            <button type="button" :disabled="busy || !preview || reason.trim().length < 3" class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50" @click="runChange">Đổi slug và tạo 301</button>
        </div>
    </div>
</template>
