<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    locations: Array,
});

const form = useForm({
    name: '',
    slug: '',
    location: 'header',
    description: '',
    is_active: true,
});

function generateSlug() {
    form.slug = form.name
        .toLowerCase()
        .replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g, 'a')
        .replace(/[èéẹẻẽêềếệểễ]/g, 'e')
        .replace(/[ìíịỉĩ]/g, 'i')
        .replace(/[òóọỏõôồốộổỗơờớợởỡ]/g, 'o')
        .replace(/[ùúụủũưừứựửữ]/g, 'u')
        .replace(/[ỳýỵỷỹ]/g, 'y')
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function submit() {
    form.post('/admin/menus');
}
</script>

<template>
    <AdminLayout title="Tạo Menu mới">
        <div class="max-w-2xl">
            <div class="flex items-center gap-3 mb-6">
                <Link href="/admin/menus" class="text-slate-500 hover:text-slate-400">
                    ← Quay lại
                </Link>
                <h1 class="text-2xl font-bold text-slate-200">Tạo Menu mới</h1>
            </div>

            <form @submit.prevent="submit" class="bg-slate-900 rounded-xl shadow-none p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Tên Menu</label>
                    <input
                        v-model="form.name"
                        @input="generateSlug"
                        type="text"
                        class="w-full px-3 py-2 border border-slate-700/50 rounded-lg focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                        placeholder="VD: Menu Chính"
                    >
                    <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Slug</label>
                    <input
                        v-model="form.slug"
                        type="text"
                        class="w-full px-3 py-2 border border-slate-700/50 rounded-lg focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                        placeholder="menu-chinh"
                    >
                    <p v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Vị trí</label>
                    <select
                        v-model="form.location"
                        class="w-full px-3 py-2 border border-slate-700/50 rounded-lg focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                    >
                        <option v-for="loc in locations" :key="loc.value" :value="loc.value">
                            {{ loc.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Mô tả</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full px-3 py-2 border border-slate-700/50 rounded-lg focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                        placeholder="Mô tả ngắn về menu"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="form.is_active" id="is_active" class="rounded border-slate-700/50 text-cyan-500 focus:ring-cyan-500/50">
                    <label for="is_active" class="text-sm text-slate-300">Hoạt động</label>
                </div>

                <div class="flex items-center gap-3 pt-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2 bg-cyan-600 text-white text-sm font-medium rounded-lg hover:bg-cyan-700 disabled:opacity-50"
                    >
                        Tạo Menu
                    </button>
                    <Link href="/admin/menus" class="text-sm text-slate-400 hover:text-slate-300">Hủy</Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
