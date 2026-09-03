<script setup>
const props = defineProps({
    form: { type: Object, required: true },
    relationCandidates: { type: Array, default: () => [] },
});

const blockTypes = [
    { value: 'hero_banner', label: 'Banner nổi bật' },
    { value: 'feature_cards', label: 'Thẻ tính năng' },
    { value: 'benchmark_cards', label: 'Thẻ hiệu năng' },
    { value: 'use_case_cards', label: 'Tình huống sử dụng' },
    { value: 'notice', label: 'Lưu ý' },
    { value: 'image_text', label: 'Ảnh và nội dung' },
];

const relationTypes = [
    { value: 'related', label: 'Sản phẩm liên quan' },
    { value: 'accessory', label: 'Phụ kiện đi kèm' },
    { value: 'frequently_bought', label: 'Mua kèm thường xuyên' },
    { value: 'alternative', label: 'Lựa chọn thay thế' },
];

function move(items, index, direction) {
    const target = index + direction;
    if (target < 0 || target >= items.length) return;
    [items[index], items[target]] = [items[target], items[index]];
}

function addHighlight() {
    props.form.highlights.push({ title: '', icon: '', is_active: true });
}

function newPayload(type) {
    if (['feature_cards', 'benchmark_cards', 'use_case_cards'].includes(type)) return { cards: [] };
    return { description: '', image_url: '', alt: '' };
}

function addBlock() {
    props.form.detail_blocks.push({
        type: 'feature_cards',
        title: '',
        payload: newPayload('feature_cards'),
        is_active: true,
    });
}

function changeBlockType(block) {
    block.payload = newPayload(block.type);
}

function cards(block) {
    if (!Array.isArray(block.payload?.cards)) block.payload = { ...(block.payload || {}), cards: [] };
    return block.payload.cards;
}

function addCard(block) {
    cards(block).push({ title: '', value: '', description: '' });
}

function addRelation() {
    props.form.relations.push({ related_product_id: '', relation_type: 'related' });
}
</script>

<template>
    <section class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-7">
        <div>
            <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Nội dung trang chi tiết</h4>
            <p class="mt-1 text-xs text-slate-400">Các khối này xuất hiện trên trang chi tiết sản phẩm. Chỉ nội dung đã nhập mới hiển thị ở website.</p>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div><h5 class="text-sm font-medium text-slate-200">Điểm nổi bật</h5><p class="text-xs text-slate-500">Mỗi dòng là một lợi ích ngắn, có thể sắp xếp lại.</p></div>
                <button type="button" @click="addHighlight" class="px-3 py-2 rounded-lg border border-cyan-500/40 text-cyan-300 text-sm hover:bg-cyan-500/10">+ Thêm điểm nổi bật</button>
            </div>
            <div v-if="!form.highlights.length" class="rounded-lg border border-dashed border-slate-700 px-4 py-3 text-sm text-slate-500">Chưa có điểm nổi bật.</div>
            <div v-for="(highlight, index) in form.highlights" :key="highlight.id || `new-highlight-${index}`" class="grid grid-cols-1 gap-2 rounded-lg border border-slate-800 bg-slate-950/50 p-3 md:grid-cols-[1fr_170px_auto]">
                <input v-model.trim="highlight.title" placeholder="Ví dụ: Bảo hành chính hãng 36 tháng" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <input v-model.trim="highlight.icon" placeholder="Tên icon / emoji (tuỳ chọn)" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <div class="flex items-center justify-end gap-2">
                    <label class="inline-flex items-center gap-1 text-xs text-slate-400"><input v-model="highlight.is_active" type="checkbox" class="rounded border-slate-700"> Hiện</label>
                    <button type="button" @click="move(form.highlights, index, -1)" :disabled="index === 0" class="text-xs text-slate-400 disabled:opacity-30">↑</button>
                    <button type="button" @click="move(form.highlights, index, 1)" :disabled="index === form.highlights.length - 1" class="text-xs text-slate-400 disabled:opacity-30">↓</button>
                    <button type="button" @click="form.highlights.splice(index, 1)" class="text-xs text-red-400 hover:text-red-300">Xóa</button>
                </div>
            </div>
        </div>

        <div class="space-y-3 border-t border-slate-800 pt-6">
            <div class="flex items-center justify-between gap-3">
                <div><h5 class="text-sm font-medium text-slate-200">Khối nội dung</h5><p class="text-xs text-slate-500">Banner, thẻ tính năng/hiệu năng, tình huống dùng hoặc lưu ý.</p></div>
                <button type="button" @click="addBlock" class="px-3 py-2 rounded-lg border border-cyan-500/40 text-cyan-300 text-sm hover:bg-cyan-500/10">+ Thêm khối</button>
            </div>
            <div v-if="!form.detail_blocks.length" class="rounded-lg border border-dashed border-slate-700 px-4 py-3 text-sm text-slate-500">Chưa có khối nội dung.</div>
            <article v-for="(block, index) in form.detail_blocks" :key="block.id || `new-block-${index}`" class="rounded-lg border border-slate-800 bg-slate-950/50 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-slate-400">Khối {{ index + 1 }}</span>
                    <div class="flex items-center gap-2"><label class="inline-flex items-center gap-1 text-xs text-slate-400"><input v-model="block.is_active" type="checkbox" class="rounded border-slate-700"> Hiện</label><button type="button" @click="move(form.detail_blocks, index, -1)" :disabled="index === 0" class="text-xs text-slate-400 disabled:opacity-30">↑</button><button type="button" @click="move(form.detail_blocks, index, 1)" :disabled="index === form.detail_blocks.length - 1" class="text-xs text-slate-400 disabled:opacity-30">↓</button><button type="button" @click="form.detail_blocks.splice(index, 1)" class="text-xs text-red-400 hover:text-red-300">Xóa</button></div>
                </div>
                <div class="grid gap-3 md:grid-cols-2"><label><span class="mb-1 block text-xs text-slate-400">Loại khối</span><select v-model="block.type" @change="changeBlockType(block)" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option v-for="type in blockTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></label><label><span class="mb-1 block text-xs text-slate-400">Tiêu đề</span><input v-model.trim="block.title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></label></div>
                <template v-if="['feature_cards', 'benchmark_cards', 'use_case_cards'].includes(block.type)">
                    <div v-if="!cards(block).length" class="text-xs text-slate-500">Chưa có thẻ trong khối này.</div>
                    <div v-for="(card, cardIndex) in cards(block)" :key="cardIndex" class="grid gap-2 rounded border border-slate-800 p-3 md:grid-cols-[1fr_160px_1fr_auto]"><input v-model.trim="card.title" placeholder="Tiêu đề" class="border border-slate-700/50 rounded px-2 py-1.5 text-sm"><input v-model.trim="card.value" placeholder="Chỉ số / nhãn" class="border border-slate-700/50 rounded px-2 py-1.5 text-sm"><input v-model.trim="card.description" placeholder="Mô tả" class="border border-slate-700/50 rounded px-2 py-1.5 text-sm"><button type="button" @click="cards(block).splice(cardIndex, 1)" class="text-xs text-red-400">Xóa</button></div>
                    <button type="button" @click="addCard(block)" class="text-sm text-cyan-300 hover:text-cyan-200">+ Thêm thẻ</button>
                </template>
                <template v-else>
                    <textarea v-model="block.payload.description" rows="3" placeholder="Nội dung hiển thị" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea>
                    <div v-if="['hero_banner', 'image_text'].includes(block.type)" class="grid gap-3 md:grid-cols-2"><input v-model.trim="block.payload.image_url" placeholder="URL ảnh (tuỳ chọn)" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><input v-model.trim="block.payload.alt" placeholder="Mô tả ảnh" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                </template>
            </article>
        </div>

        <div class="space-y-3 border-t border-slate-800 pt-6">
            <div class="flex items-center justify-between gap-3"><div><h5 class="text-sm font-medium text-slate-200">Sản phẩm liên quan và mua kèm</h5><p class="text-xs text-slate-500">Có thể để trống; website sẽ chỉ hiển thị mục có dữ liệu hợp lệ.</p></div><button type="button" @click="addRelation" class="px-3 py-2 rounded-lg border border-cyan-500/40 text-cyan-300 text-sm hover:bg-cyan-500/10">+ Thêm liên kết</button></div>
            <div v-if="!form.relations.length" class="rounded-lg border border-dashed border-slate-700 px-4 py-3 text-sm text-slate-500">Chưa chọn sản phẩm liên quan.</div>
            <div v-for="(relation, index) in form.relations" :key="relation.id || `new-relation-${index}`" class="grid grid-cols-1 gap-2 rounded-lg border border-slate-800 bg-slate-950/50 p-3 md:grid-cols-[1fr_220px_auto]"><select v-model="relation.related_product_id" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Chọn sản phẩm…</option><option v-for="candidate in relationCandidates" :key="candidate.id" :value="candidate.id">{{ candidate.name }}{{ candidate.sku ? ` · ${candidate.sku}` : '' }}</option></select><select v-model="relation.relation_type" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option v-for="type in relationTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select><div class="flex items-center justify-end gap-2"><button type="button" @click="move(form.relations, index, -1)" :disabled="index === 0" class="text-xs text-slate-400 disabled:opacity-30">↑</button><button type="button" @click="move(form.relations, index, 1)" :disabled="index === form.relations.length - 1" class="text-xs text-slate-400 disabled:opacity-30">↓</button><button type="button" @click="form.relations.splice(index, 1)" class="text-xs text-red-400">Xóa</button></div></div>
        </div>
    </section>
</template>
