<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({
    settings: Object,
});

// Group labels in Vietnamese
const groupLabels = {
    general: 'Thông tin chung',
    contact: 'Liên hệ',
    social: 'Mạng xã hội',
    seo: 'SEO',
    homepage: 'Trang chủ',
    payment: 'Thanh toán',
    shipping: 'Vận chuyển',
    storefront: 'Trang chi tiết sản phẩm',
    ai: 'AI (ChatGPT / Gemini)',
};

const groupIcons = {
    general: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    contact: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    social: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    seo: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    homepage: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    payment: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    shipping: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
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
const processing = ref(false);
const flash = ref(null);

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
    form.put('/admin/settings', {
        preserveScroll: true,
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

                                    <!-- Image picker (Logo, Favicon, etc.) -->
                                    <template v-if="item.type === 'image'">
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
                                    <template v-else-if="item.type === 'text' || item.type === 'color' || item.type === 'password'">
                                        <input
                                            v-model="formData[item.key]"
                                            :type="item.type === 'color' ? 'color' : item.type === 'password' ? 'password' : 'text'"
                                            :placeholder="item.label"
                                            :class="[
                                                'w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50',
                                                item.type === 'color' ? 'h-10 p-1' : ''
                                            ]"
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
                                    <p v-if="item.key === 'storefront_warehouse_addresses'" class="text-xs text-slate-500 mt-1">Mỗi kho một nhóm dòng; có thể dùng ký hiệu 📍 và ☎, xuống dòng sẽ được giữ nguyên ngoài trang sản phẩm.</p>
                                    <p v-else-if="item.key === 'storefront_warranty_information'" class="text-xs text-slate-500 mt-1">Nhập từng chính sách trên một dòng; có thể dùng ✅ hoặc dấu gạch đầu dòng. Không cần nhập HTML.</p>

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

                                    <p class="text-xs text-slate-500 mt-1">{{ item.key }}</p>
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
