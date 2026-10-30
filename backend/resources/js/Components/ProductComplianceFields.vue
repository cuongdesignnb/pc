<script setup>
// Product information Bộ Công Thương requires (Nghị định 37/2026, Phụ lục I).
// On a category the fields are defaults for its products; empty text falls
// back to the parent category, then to Cài đặt → Trang chi tiết sản phẩm.
defineProps({
    form: { type: Object, required: true },
    category: { type: Boolean, default: false },
});
</script>

<template>
    <fieldset class="rounded-lg border border-slate-700/50 p-4 space-y-3">
        <legend class="px-1 text-sm font-semibold text-slate-200">Thông tin theo quy định (Nghị định 37/2026)</legend>
        <div class="grid gap-3" :class="category ? '' : 'md:grid-cols-2'">
            <div v-if="!category">
                <label class="block text-sm font-medium text-slate-300 mb-1">Năm sản xuất</label>
                <input v-model="form.manufacture_year" type="number" min="1990" max="2100" placeholder="VD: 2021" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <p v-if="form.errors.manufacture_year" class="text-red-400 text-xs mt-1">{{ form.errors.manufacture_year }}</p>
            </div>
            <div v-if="!category">
                <label class="block text-sm font-medium text-slate-300 mb-1">Sản phẩm tân trang làm mới</label>
                <select v-model="form.is_refurbished" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option :value="null">Theo danh mục</option>
                    <option :value="true">Có – ghi "Sản phẩm tân trang làm mới"</option>
                    <option :value="false">Không</option>
                </select>
            </div>
            <label v-else class="flex items-center gap-2 text-sm text-slate-300">
                <input v-model="form.is_refurbished" type="checkbox" class="rounded border-slate-700/50 text-cyan-500">
                Sản phẩm thuộc danh mục này (và danh mục con) là "Sản phẩm tân trang làm mới"
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Thông tin cảnh báo</label>
            <textarea v-model="form.warning_info" rows="3" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" :placeholder="category ? 'Để trống thì dùng danh mục cha hoặc nội dung mặc định trong Cài đặt' : 'Để trống thì dùng nội dung của danh mục'" />
            <p v-if="form.errors.warning_info" class="text-red-400 text-xs mt-1">{{ form.errors.warning_info }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Hướng dẫn sử dụng, bảo quản</label>
            <textarea v-model="form.usage_instructions" rows="4" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" :placeholder="category ? 'Để trống thì dùng danh mục cha hoặc nội dung mặc định trong Cài đặt' : 'Để trống thì dùng nội dung của danh mục'" />
            <p v-if="form.errors.usage_instructions" class="text-red-400 text-xs mt-1">{{ form.errors.usage_instructions }}</p>
        </div>
    </fieldset>
</template>
