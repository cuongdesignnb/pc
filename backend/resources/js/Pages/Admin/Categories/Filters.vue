<script setup>
import { ref } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    category: Object,
    allFilters: Array,
    assignedFilterIds: Array,
});

const flash = usePage().props.flash;
const selected = ref([...props.assignedFilterIds]);

function toggle(filterId) {
    const idx = selected.value.indexOf(filterId);
    if (idx > -1) selected.value.splice(idx, 1);
    else selected.value.push(filterId);
}

function moveFilter(index, dir) {
    const newIndex = index + dir;
    if (newIndex < 0 || newIndex >= selected.value.length) return;
    [selected.value[index], selected.value[newIndex]] = [selected.value[newIndex], selected.value[index]];
}

const form = useForm({});
function submit() {
    form.transform(() => ({ filter_ids: selected.value }))
        .put(`/admin/categories/${props.category.id}/filters`);
}
</script>
<template>
<AdminLayout :title="`Bộ lọc: ${category.name}`">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-semibold text-slate-200">Gán bộ lọc cho: {{ category.name }}</h3>
                <p class="text-sm text-slate-400 mt-0.5">Chọn các bộ lọc sẽ hiển thị khi khách vào danh mục này</p>
            </div>
            <Link href="/admin/categories" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại danh mục</Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Available filters -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-4">Tất cả bộ lọc có sẵn</h4>
                <div v-if="allFilters.length" class="space-y-2">
                    <label v-for="f in allFilters" :key="f.id"
                        class="flex items-center gap-3 p-3 rounded-lg border transition-colors cursor-pointer"
                        :class="selected.includes(f.id) ? 'border-indigo-300 bg-cyan-500/10' : 'border-slate-800/60 hover:bg-slate-800/40'">
                        <input type="checkbox" :checked="selected.includes(f.id)" @change="toggle(f.id)"
                            class="rounded border-slate-700/50 text-cyan-500 focus:ring-cyan-500/50">
                        <div class="flex-1">
                            <span class="text-sm font-medium text-slate-200">{{ f.name }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ f.values_count }} giá trị)</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="f.type === 'price_range' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">
                            {{ f.type === 'price_range' ? 'Khoảng giá' : 'Checkbox' }}
                        </span>
                    </label>
                </div>
                <p v-else class="text-sm text-slate-500 text-center py-4">
                    Chưa có bộ lọc nào. <Link href="/admin/filters/create" class="text-cyan-500 hover:text-indigo-800">Tạo bộ lọc mới →</Link>
                </p>
            </div>

            <!-- Selected order -->
            <div v-if="selected.length" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-4">Thứ tự hiển thị (kéo để sắp xếp)</h4>
                <div class="space-y-1.5">
                    <div v-for="(fId, idx) in selected" :key="fId"
                        class="flex items-center gap-3 p-2.5 bg-slate-800/40 rounded-lg border border-slate-800/60">
                        <span class="text-xs text-slate-500 w-6 text-center">{{ idx + 1 }}</span>
                        <span class="text-sm font-medium text-slate-200 flex-1">
                            {{ allFilters.find(f => f.id === fId)?.name || `Filter #${fId}` }}
                        </span>
                        <div class="flex gap-1">
                            <button type="button" @click="moveFilter(idx, -1)" :disabled="idx === 0"
                                class="p-1 text-slate-500 hover:text-slate-400 disabled:opacity-30">↑</button>
                            <button type="button" @click="moveFilter(idx, 1)" :disabled="idx === selected.length - 1"
                                class="p-1 text-slate-500 hover:text-slate-400 disabled:opacity-30">↓</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <Link href="/admin/categories" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">
                    Lưu bộ lọc
                </button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
