<script setup>
import { ref } from 'vue';

const props = defineProps({
    type: { type: String, default: 'article' },
    topic: { type: String, default: '' },
    productId: { type: [Number, String], default: null },
    existingContent: { type: String, default: '' },
});
const emit = defineEmits(['generated']);

const open = ref(false);
const loading = ref(false);
const error = ref('');
const warnings = ref([]);
const form = ref({ topic: props.topic, keywords: '', tone: 'professional', length: 'medium', with_images: false, image_count: 0 });

function show() {
    form.value.topic = props.topic || form.value.topic;
    error.value = '';
    warnings.value = [];
    open.value = true;
}

async function generate() {
    if (!form.value.topic.trim()) { error.value = 'Vui lòng nhập chủ đề hoặc tên sản phẩm.'; return; }
    loading.value = true;
    error.value = '';
    warnings.value = [];
    try {
        const endpoint = form.value.with_images ? '/api/ai/content-with-images' : '/api/ai/content';
        const { data } = await window.axios.post(endpoint, {
            topic: form.value.topic,
            type: props.type,
            keywords: form.value.keywords,
            tone: form.value.tone,
            length: form.value.length,
            full_article: true,
            with_images: form.value.with_images,
            image_count: Number(form.value.image_count),
            existing_content: props.existingContent,
            product_id: props.productId || null,
        }, { headers: { Accept: 'application/json' } });
        if (!data.success) throw new Error(data.message || data.error || 'Không thể tạo nội dung AI.');
        warnings.value = data.warnings || [];
        emit('generated', data);
        open.value = false;
    } catch (e) {
        error.value = e.response?.data?.message || e.response?.data?.error || e.message || 'Không thể kết nối AI.';
    } finally { loading.value = false; }
}
</script>

<template>
    <button type="button" @click="show" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-sm font-semibold rounded-lg hover:from-violet-600 hover:to-purple-700 transition-all shadow-lg shadow-violet-500/20">
        <span>✨</span> Viết bằng AI
    </button>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/60 p-4" @click.self="open = false">
            <div class="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-5"><h3 class="text-base font-bold text-slate-200">Viết nội dung bằng AI</h3><button type="button" @click="open = false" class="text-slate-400 hover:text-white">✕</button></div>
                <div v-if="error" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">{{ error }}</div>
                <div class="space-y-4">
                    <label class="block text-sm text-slate-300">Chủ đề / tên sản phẩm<input v-model="form.topic" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm" /></label>
                    <label class="block text-sm text-slate-300">Từ khóa SEO (không bắt buộc)<textarea v-model="form.keywords" rows="2" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm" /></label>
                    <div class="grid grid-cols-2 gap-3"><label class="text-sm text-slate-300">Giọng văn<select v-model="form.tone" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2"><option value="professional">Chuyên nghiệp</option><option value="casual">Thân thiện</option><option value="luxury">Cao cấp</option></select></label><label class="text-sm text-slate-300">Độ dài<select v-model="form.length" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2"><option value="short">Ngắn</option><option value="medium">Vừa</option><option value="long">Dài</option></select></label></div>
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="form.with_images" type="checkbox" class="rounded text-violet-500" /> Sinh thêm ảnh minh họa</label>
                    <label v-if="form.with_images" class="block text-sm text-slate-300">Số ảnh trong nội dung (0–10)<input v-model.number="form.image_count" type="number" min="0" max="10" class="mt-1 w-28 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2" /></label>
                    <p v-if="warnings.length" class="text-xs text-amber-300">{{ warnings.join(' ') }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" @click="open = false" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300">Hủy</button><button type="button" @click="generate" :disabled="loading" class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ loading ? 'Đang viết…' : 'Tạo nội dung' }}</button></div>
            </div>
        </div>
    </Teleport>
</template>
