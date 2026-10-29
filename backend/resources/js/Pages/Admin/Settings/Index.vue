<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({
    settings: Object,
    categories: { type: Array, default: () => [] },
});

// Group labels in Vietnamese
const groupLabels = {
    general: 'Thông tin chung',
    appearance: 'Giao diện',
    contact: 'Liên hệ',
    social: 'Mạng xã hội',
    seo: 'SEO',
    homepage: 'Trang chủ',
    payment: 'Thanh toán',
    shipping: 'Vận chuyển',
    storefront: 'Trang chi tiết sản phẩm',
    smtp: 'Email / SMTP',
    ai: 'AI (ChatGPT / Gemini)',
};

const groupIcons = {
    general: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    appearance: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01',
    contact: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    social: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    seo: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    homepage: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    payment: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    shipping: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
    smtp: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    ai: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
};

const groups = computed(() => Object.keys(props.settings || {}));
const activeTab = ref(groups.value[0] || 'general');

// Build form data from settings
function buildFormData() {
    const data = {};
    for (const [group, items] of Object.entries(props.settings || {})) {
        for (const item of items) {
            data[item.key] = item.value ?? '';
        }
    }
    return data;
}

const formData = ref(buildFormData());

// Homepage product tabs and category sections are stored as JSON but edited
// as rows of "tab name + category".
function parseList(value) {
    try {
        const parsed = typeof value === 'string' ? JSON.parse(value || '[]') : value;
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}
const productTabs = ref(parseList(formData.value.homepage_product_tabs).map(tab => ({ label: tab.label || '', category_slug: tab.category_slug || '' })));
watch(productTabs, rows => {
    formData.value.homepage_product_tabs = JSON.stringify(rows.filter(row => row.category_slug));
}, { deep: true });
const featuredSections = ref(parseList(formData.value.homepage_featured_category_slugs).filter(slug => typeof slug === 'string').map(slug => ({ slug })));
watch(featuredSections, rows => {
    formData.value.homepage_featured_category_slugs = JSON.stringify(rows.map(row => row.slug).filter(Boolean));
}, { deep: true });
const categoryOptions = computed(() => {
    const byParent = {};
    for (const category of props.categories) (byParent[category.parent_id || 0] ||= []).push(category);
    const out = [];
    const walk = (parentId, depth) => (byParent[parentId] || []).forEach(category => {
        out.push({ slug: category.slug, label: `${'— '.repeat(depth)}${category.name}` });
        walk(category.id, depth + 1);
    });
    walk(0, 0);
    return out;
});
function moveRow(rows, index, offset) {
    const target = index + offset;
    if (target < 0 || target >= rows.length) return;
    [rows[index], rows[target]] = [rows[target], rows[index]];
}
const processing = ref(false);
const flash = ref(null);
const saveErrors = ref([]);
const testEmail = ref('');
const testingSmtp = ref(false);

function submit() {
    processing.value = true;
    const allSettings = [];

    for (const [group, items] of Object.entries(props.settings || {})) {
        for (const item of items) {
            allSettings.push({
                key: item.key,
                value: formData.value[item.key],
            });
        }
    }

    const form = useForm({ settings: allSettings });
    saveErrors.value = [];
    form.put('/admin/settings', {
        preserveScroll: true,
        onError: (errors) => {
            saveErrors.value = Object.values(errors);
        },
        onSuccess: () => {
            flash.value = 'Đã lưu cài đặt thành công!';
            setTimeout(() => { flash.value = null; }, 3000);
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function getSettingsForGroup(group) {
    return props.settings?.[group] || [];
}

// Mirrors StorefrontSmtpMailer: what is still missing before new-order
// emails can go out (password shows as ******** once saved).
const isOn = (value) => ['1', 'true', 'on', 'yes', true, 1].includes(typeof value === 'string' ? value.toLowerCase() : value);
const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
const smtpChecklist = computed(() => {
    const f = formData.value;
    const sender = String(f.smtp_from_address || '').trim() || (isEmail(f.smtp_username) ? f.smtp_username : '') || f.contact_email;
    const recipient = String(f.smtp_order_recipient || '').trim() || f.contact_email || sender;
    return [
        { label: 'Bật gửi email SMTP', ok: isOn(f.smtp_enabled) },
        { label: 'Máy chủ và cổng SMTP', ok: !!String(f.smtp_host || '').trim() && Number(f.smtp_port) > 0 },
        { label: 'Tài khoản và mật khẩu SMTP', ok: !!String(f.smtp_username || '').trim() && !!String(f.smtp_password || '').trim() },
        { label: 'Email người gửi hợp lệ', ok: isEmail(sender) },
        { label: 'Email nhận thông báo đơn hàng', ok: String(recipient).split(/[\s,;]+/).some(isEmail) },
        { label: 'Bật thông báo đơn hàng mới', ok: isOn(f.smtp_order_notifications_enabled) },
    ];
});

function sendSmtpTest() {
    if (!testEmail.value.trim()) {
        flash.value = 'Nhập email nhận thử trước khi gửi.';
        return;
    }

    testingSmtp.value = true;
    router.post('/admin/settings/test-smtp', { recipient: testEmail.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => {
            flash.value = 'Đã gửi yêu cầu email thử. Kiểm tra hộp thư và Spam.';
        },
        onFinish: () => {
            testingSmtp.value = false;
        },
    });
}
</script>

<template>
<AdminLayout title="Cài đặt website">
    <div class="max-w-5xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-200">Cài đặt website</h3>
                <p class="text-sm text-slate-400 mt-1">Quản lý thông tin và cấu hình chung cho website</p>
            </div>
        </div>

        <!-- Validation errors -->
        <div v-if="saveErrors.length" class="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm space-y-1">
            <p v-for="message in saveErrors" :key="message">{{ message }}</p>
        </div>

        <!-- Success flash -->
        <div v-if="flash" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ flash }}
        </div>

        <div class="flex gap-6">
            <!-- Sidebar tabs -->
            <div class="w-52 flex-shrink-0">
                <nav class="sticky top-20 space-y-1">
                    <button
                        v-for="group in groups"
                        :key="group"
                        @click="activeTab = group"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors text-left',
                            activeTab === group
                                ? 'bg-cyan-500/10 text-cyan-400'
                                : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                        ]"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="groupIcons[group] || groupIcons.general" />
                        </svg>
                        {{ groupLabels[group] || group }}
                    </button>
                </nav>
            </div>

            <!-- Settings form -->
            <div class="flex-1">
                <form @submit.prevent="submit">
                    <template v-for="group in groups" :key="group">
                        <div v-show="activeTab === group" class="bg-slate-900 rounded-xl shadow-none border border-slate-800/60 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-800/40 bg-slate-800/40">
                                <h4 class="text-base font-semibold text-slate-200">{{ groupLabels[group] || group }}</h4>
                            </div>

                            <div class="p-6 space-y-5">
                                <div v-for="item in getSettingsForGroup(group)" :key="item.key">
                                    <label class="block text-sm font-medium text-slate-300 mb-1.5">
                                        {{ item.label }}
                                        <span v-if="!item.is_public" class="text-xs text-slate-500 ml-1">(nội bộ)</span>
                                    </label>

                                    <!-- Homepage product tabs: tab name + category -->
                                    <div v-if="item.key === 'homepage_product_tabs'" class="space-y-2">
                                        <div v-for="(tab, index) in productTabs" :key="index" class="flex items-center gap-2">
                                            <input v-model="tab.label" type="text" maxlength="60" placeholder="Tên tab (để trống = tên danh mục)" class="w-56 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                                            <select v-model="tab.category_slug" class="flex-1 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                                                <option value="">— Chọn danh mục —</option>
                                                <option v-for="option in categoryOptions" :key="option.slug" :value="option.slug">{{ option.label }}</option>
                                            </select>
                                            <button type="button" class="px-2 text-slate-400 hover:text-slate-200" title="Lên" @click="moveRow(productTabs, index, -1)">↑</button>
                                            <button type="button" class="px-2 text-slate-400 hover:text-slate-200" title="Xuống" @click="moveRow(productTabs, index, 1)">↓</button>
                                            <button type="button" class="text-xs text-red-400 hover:text-red-300" @click="productTabs.splice(index, 1)">Xóa</button>
                                        </div>
                                        <button v-if="productTabs.length < 6" type="button" class="text-xs text-cyan-500 hover:text-cyan-400" @click="productTabs.push({ label: '', category_slug: '' })">+ Thêm tab</button>
                                        <p class="text-xs text-slate-500">Mỗi tab hiện sản phẩm bán chạy của một danh mục (gồm cả danh mục con). Không có tab nào thì khối này được ẩn.</p>
                                    </div>

                                    <!-- Homepage category sections -->
                                    <div v-else-if="item.key === 'homepage_featured_category_slugs'" class="space-y-2">
                                        <div v-for="(row, index) in featuredSections" :key="index" class="flex items-center gap-2">
                                            <select v-model="row.slug" class="flex-1 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                                                <option value="">— Chọn danh mục —</option>
                                                <option v-for="option in categoryOptions" :key="option.slug" :value="option.slug">{{ option.label }}</option>
                                            </select>
                                            <button type="button" class="px-2 text-slate-400 hover:text-slate-200" title="Lên" @click="moveRow(featuredSections, index, -1)">↑</button>
                                            <button type="button" class="px-2 text-slate-400 hover:text-slate-200" title="Xuống" @click="moveRow(featuredSections, index, 1)">↓</button>
                                            <button type="button" class="text-xs text-red-400 hover:text-red-300" @click="featuredSections.splice(index, 1)">Xóa</button>
                                        </div>
                                        <button type="button" class="text-xs text-cyan-500 hover:text-cyan-400" @click="featuredSections.push({ slug: '' })">+ Thêm khối danh mục</button>
                                        <p class="text-xs text-slate-500">Mỗi danh mục thành một khối sản phẩm trên trang chủ (ví dụ LAPTOP CŨ, LAPTOP MỚI, LINH KIỆN LAPTOP); danh mục con hiện thành nút lọc nhanh.</p>
                                    </div>

                                    <!-- Colour: picker + hex code -->
                                    <div v-else-if="item.type === 'color'" class="flex items-center gap-3">
                                        <input v-model="formData[item.key]" type="color" class="h-10 w-16 border border-slate-700/50 rounded-lg p-1">
                                        <input v-model="formData[item.key]" type="text" maxlength="7" placeholder="#RRGGBB" class="w-32 border border-slate-700/50 rounded-lg px-3 py-2 text-sm font-mono uppercase">
                                    </div>

                                    <!-- Image picker (Logo, Favicon, etc.) -->
                                    <template v-else-if="item.type === 'image'">
                                        <div class="flex items-start gap-4">
                                            <!-- Preview -->
                                            <div v-if="formData[item.key]" class="relative group flex-shrink-0">
                                                <div class="w-28 h-28 rounded-xl border border-slate-700/50 bg-slate-800/60 flex items-center justify-center p-2 overflow-hidden">
                                                    <img :src="formData[item.key]" :alt="item.label" class="max-w-full max-h-full object-contain" />
                                                </div>
                                                <!-- Remove button -->
                                                <button
                                                    type="button"
                                                    @click="formData[item.key] = ''"
                                                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500/90 hover:bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-200 shadow-lg"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <!-- Empty state -->
                                            <div v-else class="w-28 h-28 rounded-xl border-2 border-dashed border-slate-700/50 bg-slate-800/30 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                            <!-- Picker controls -->
                                            <div class="flex flex-col gap-2 pt-1">
                                                <MediaPicker
                                                    v-model="formData[item.key]"
                                                    :label="''"
                                                />
                                                <p class="text-xs text-slate-500">Chọn từ thư viện Media hoặc upload ảnh mới</p>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Text / Color input -->
                                    <template v-else-if="item.type === 'text' || item.type === 'password'">
                                        <input
                                            v-model="formData[item.key]"
                                            :type="item.type === 'password' ? 'password' : 'text'"
                                            :placeholder="item.label"
                                            class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                                        />
                                        <p v-if="item.type === 'password'" class="text-xs text-slate-500 mt-1">Để trống để giữ key hiện tại. Key chỉ được dùng ở backend, không gửi ra trình duyệt.</p>
                                    </template>

                                    <!-- Textarea -->
                                    <textarea
                                        v-else-if="item.type === 'textarea'"
                                        v-model="formData[item.key]"
                                        :placeholder="item.label"
                                        :rows="item.key === 'storefront_warehouse_addresses' || item.key === 'storefront_warranty_information' ? 8 : 3"
                                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                                    />

                                    <!-- Boolean toggle -->
                                    <label v-else-if="item.type === 'boolean'" class="relative inline-flex items-center cursor-pointer mt-1">
                                        <input
                                            type="checkbox"
                                            :checked="formData[item.key] === '1' || formData[item.key] === true || formData[item.key] === 'true'"
                                            @change="formData[item.key] = $event.target.checked ? '1' : '0'"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-900 after:border-slate-700/50 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                                        <span class="ml-3 text-sm text-slate-400">{{ formData[item.key] === '1' || formData[item.key] === true || formData[item.key] === 'true' ? 'Bật' : 'Tắt' }}</span>
                                    </label>

                                    <!-- Number -->
                                    <input
                                        v-else-if="item.type === 'number'"
                                        v-model="formData[item.key]"
                                        type="number"
                                        :placeholder="item.label"
                                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                                    />

                                    <!-- Select -->
                                    <select
                                        v-else-if="item.type === 'select' && item.options"
                                        v-model="formData[item.key]"
                                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                                    >
                                        <option v-for="opt in (typeof item.options === 'string' ? JSON.parse(item.options) : item.options)?.choices || []" :key="opt" :value="opt">
                                            {{ opt }}
                                        </option>
                                    </select>

                                    <!-- Fallback text -->
                                    <input
                                        v-else
                                        v-model="formData[item.key]"
                                        type="text"
                                        :placeholder="item.label"
                                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50"
                                    />

                                    <!-- Field hints: kept after the input chain so their v-if/v-else-if
                                         cannot capture the input branches above. -->
                                    <p v-if="item.key === 'storefront_warehouse_addresses'" class="text-xs text-slate-500 mt-1">Mỗi kho một nhóm dòng; có thể dùng ký hiệu 📍 và ☎, xuống dòng sẽ được giữ nguyên ngoài trang sản phẩm.</p>
                                    <p v-else-if="item.key === 'storefront_warranty_information'" class="text-xs text-slate-500 mt-1">Nhập từng chính sách trên một dòng; có thể dùng ✅ hoặc dấu gạch đầu dòng. Không cần nhập HTML.</p>
                                    <p v-else-if="item.key === 'ai_product_research_official_domains'" class="text-xs text-slate-500 mt-1">Mỗi dòng một thương hiệu: <code class="text-slate-300">NVIDIA=nvidia.com</code>. Chỉ URL thuộc domain này mới được lưu làm nguồn kiểm chứng.</p>
                                    <p v-else-if="item.key === 'contact_map_embed_url'" class="text-xs text-slate-500 mt-1">Trên Google Maps chọn Chia sẻ → Nhúng bản đồ → Sao chép HTML rồi dán vào đây (dán cả thẻ iframe cũng được). Bản đồ hiện ở trang Liên hệ và cột "Bản đồ cửa hàng" ở chân trang; để trống để ẩn.</p>
                                    <p v-else-if="item.key === 'social_facebook'" class="text-xs text-slate-500 mt-1">Link Fanpage (https://www.facebook.com/...) dùng cho khung Fanpage ở chân trang.</p>
                                    <p v-else-if="item.key === 'social_youtube'" class="text-xs text-slate-500 mt-1">Link kênh YouTube dùng cho khung kênh ở chân trang và nút "Xem kênh YouTube" của khối Hoạt động tại cửa hàng.</p>
                                    <p v-else-if="item.key === 'storefront_product_contact_footer_website_url' || item.key === 'storefront_product_contact_footer_maps_url'" class="text-xs text-slate-500 mt-1">Dùng URL HTTPS đầy đủ. Footer mặc định tắt và chỉ được thêm vào sản phẩm khi campaign bật lựa chọn này.</p>

                                    <p class="text-xs text-slate-500 mt-1">{{ item.key }}</p>
                                </div>

                                <div v-if="group === 'smtp'" class="rounded-lg border border-slate-700/50 p-4">
                                    <h5 class="text-sm font-semibold text-slate-200">Email báo đơn hàng mới</h5>
                                    <ul class="mt-2 space-y-1 text-sm">
                                        <li v-for="check in smtpChecklist" :key="check.label" :class="check.ok ? 'text-emerald-400' : 'text-amber-400'">
                                            {{ check.ok ? '✓' : '✗' }} {{ check.label }}
                                        </li>
                                    </ul>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">
                                        Để trống "Email người gửi" thì dùng tài khoản SMTP; để trống "Email nhận" thì gửi về email liên hệ của cửa hàng.
                                        Gmail cần "Mật khẩu ứng dụng" (không dùng mật khẩu đăng nhập), máy chủ smtp.gmail.com, cổng 587, mã hóa TLS.
                                    </p>
                                </div>
                                <div v-if="group === 'smtp'" class="rounded-lg border border-cyan-500/20 bg-cyan-500/5 p-4">
                                    <h5 class="text-sm font-semibold text-cyan-300">Kiểm tra cấu hình SMTP</h5>
                                    <p class="mt-1 text-xs leading-5 text-slate-400">Lưu cài đặt SMTP trước, sau đó gửi email thử để xác nhận máy chủ có thể gửi thư.</p>
                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        <input v-model="testEmail" type="email" placeholder="email-nhan-thu@example.com" class="min-w-0 flex-1 rounded-lg border border-slate-700/50 px-3 py-2 text-sm focus:border-cyan-500/50 focus:ring-2 focus:ring-cyan-500/50" />
                                        <button type="button" :disabled="testingSmtp" class="rounded-lg border border-cyan-500/60 px-4 py-2 text-sm font-semibold text-cyan-300 hover:bg-cyan-500/10 disabled:opacity-50" @click="sendSmtpTest">
                                            {{ testingSmtp ? 'Đang gửi…' : 'Gửi email thử' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Save button -->
                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="px-6 py-2.5 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-semibold disabled:opacity-50 transition-colors shadow-none"
                        >
                            <span v-if="processing" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Đang lưu...
                            </span>
                            <span v-else>Lưu cài đặt</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
