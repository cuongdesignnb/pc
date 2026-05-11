<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';
const props = defineProps({ categories: Array });
const form = useForm({ title: '', slug: '', excerpt: '', body: '', featured_image: '', post_category_id: '', status: 'draft', is_featured: false, published_at: '', meta_title: '', meta_description: '' });
function genSlug() { form.slug = form.title.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,''); }
function submit() { form.post('/admin/posts'); }

// AI Generation
const aiModal = ref(false);
const aiProvider = ref('chatgpt');
const aiLoading = ref(false);
const aiError = ref('');

async function generateWithAI() {
    if (!form.title.trim()) {
        aiError.value = 'Vui long nhap tieu de truoc khi tao bai bang AI.';
        return;
    }
    aiLoading.value = true;
    aiError.value = '';
    try {
        const response = await fetch('/admin/ai-articles/generate-single', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ keyword: form.title, provider: aiProvider.value }),
        });
        const data = await response.json();
        if (data.success && data.article) {
            const a = data.article;
            form.title = a.title || form.title;
            form.slug = a.slug || form.slug;
            form.excerpt = a.excerpt || '';
            form.body = a.body || '';
            form.meta_title = a.meta_title || '';
            form.meta_description = a.meta_description || '';
            if (a.featured_image) form.featured_image = a.featured_image;
            aiModal.value = false;
            if (window.__toast) window.__toast({ type: 'success', message: 'Da tao bai viet bang AI thanh cong!' });
        } else {
            aiError.value = data.error || 'Khong the tao bai viet. Vui long thu lai.';
        }
    } catch (e) {
        aiError.value = 'Loi ket noi. Vui long thu lai.';
    } finally {
        aiLoading.value = false;
    }
}
</script>
<template>
<AdminLayout title="Viet bai">
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-200">Viet bai moi</h3>
            <div class="flex items-center gap-2">
                <button
                    @click="aiModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-sm font-semibold rounded-lg hover:from-violet-600 hover:to-purple-700 transition-all shadow-lg shadow-violet-500/20"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Tao bang AI
                </button>
                <Link href="/admin/posts" class="text-sm text-slate-400 hover:text-slate-200 px-3 py-2">Quay lai</Link>
            </div>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
            <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300">Noi dung</h4>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Tieu de *</label><input v-model="form.title" @blur="!form.slug && genSlug()" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="Nhap tieu de bai viet"><div v-if="form.errors.title" class="text-red-400 text-xs mt-1">{{ form.errors.title }}</div></div>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Slug *</label><input v-model="form.slug" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50"><div v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</div></div>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Tom tat</label><textarea v-model="form.excerpt" rows="2" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50"></textarea></div>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Noi dung *</label><RichEditor v-model="form.body" placeholder="Viet noi dung bai viet..." min-height="300px" /><div v-if="form.errors.body" class="text-red-400 text-xs mt-1">{{ form.errors.body }}</div></div>
            </div>
            <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300">Cai dat</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Danh muc</label>
                        <select v-model="form.post_category_id" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50">
                            <option value="">-- Chon danh muc --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Trang thai *</label>
                        <select v-model="form.status" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50">
                            <option value="draft">Nhap</option><option value="published">Xuat ban</option><option value="archived">Luu tru</option>
                        </select>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Anh dai dien</label><MediaPicker v-model="form.featured_image" label="Chon anh dai dien" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-400 mb-1">Ngay dang</label><input v-model="form.published_at" type="datetime-local" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50"></div>
                    <div class="flex items-end"><label class="flex items-center gap-2 text-sm text-slate-400 pb-2"><input v-model="form.is_featured" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500"> Noi bat</label></div>
                </div>
            </div>
            <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300">SEO</h4>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Meta Title</label><input v-model="form.meta_title" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50"></div>
                <div><label class="block text-sm font-medium text-slate-400 mb-1">Meta Description</label><textarea v-model="form.meta_description" rows="2" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50"></textarea></div>
            </div>
            <div class="flex justify-end gap-3">
                <Link href="/admin/posts" class="px-5 py-2.5 text-sm text-slate-400 hover:text-slate-200 border border-slate-700 rounded-lg hover:bg-slate-800 transition-colors">Huy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white rounded-lg text-sm font-semibold disabled:opacity-40 transition-all shadow-lg shadow-cyan-500/20">Tao bai viet</button>
            </div>
        </form>
    </div>

    <!-- AI Modal -->
    <Teleport to="body">
        <div v-if="aiModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[9998] p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-slate-200">Tao bai viet bang AI</h3>
                    <button @click="aiModal = false" class="p-1 text-slate-500 hover:text-slate-300 rounded"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>

                <div v-if="aiError" class="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-sm text-red-400">{{ aiError }}</div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Tieu de / Tu khoa</label>
                        <input v-model="form.title" class="w-full bg-slate-800/60 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="VD: Top 10 laptop gaming 2025" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">AI Provider</label>
                        <div class="flex gap-3">
                            <label class="flex-1 flex items-center gap-2 px-4 py-3 rounded-lg border cursor-pointer transition-colors" :class="aiProvider === 'chatgpt' ? 'border-cyan-500/50 bg-cyan-500/10 text-cyan-400' : 'border-slate-700 text-slate-400'">
                                <input type="radio" v-model="aiProvider" value="chatgpt" class="sr-only" />
                                <span class="text-sm font-medium">ChatGPT</span>
                            </label>
                            <label class="flex-1 flex items-center gap-2 px-4 py-3 rounded-lg border cursor-pointer transition-colors" :class="aiProvider === 'gemini' ? 'border-violet-500/50 bg-violet-500/10 text-violet-400' : 'border-slate-700 text-slate-400'">
                                <input type="radio" v-model="aiProvider" value="gemini" class="sr-only" />
                                <span class="text-sm font-medium">Gemini</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="aiModal = false" class="px-4 py-2.5 text-sm text-slate-400 hover:text-slate-200 border border-slate-700 rounded-lg">Huy</button>
                    <button
                        @click="generateWithAI"
                        :disabled="aiLoading || !form.title.trim()"
                        class="px-6 py-2.5 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-sm font-semibold rounded-lg disabled:opacity-40 transition-all"
                    >
                        <span v-if="aiLoading" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Dang tao...
                        </span>
                        <span v-else>Tao bai viet</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</AdminLayout>
</template>
