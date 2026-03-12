<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
};

const groupIcons = {
    general: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    contact: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    social: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    seo: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    homepage: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    payment: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    shipping: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
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
                <h3 class="text-xl font-bold text-gray-900">Cài đặt website</h3>
                <p class="text-sm text-gray-500 mt-1">Quản lý thông tin và cấu hình chung cho website</p>
            </div>
        </div>

        <!-- Success flash -->
        <div v-if="flash" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
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
                                ? 'bg-indigo-50 text-indigo-700'
                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
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
                        <div v-show="activeTab === group" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                                <h4 class="text-base font-semibold text-gray-900">{{ groupLabels[group] || group }}</h4>
                            </div>

                            <div class="p-6 space-y-5">
                                <div v-for="item in getSettingsForGroup(group)" :key="item.key">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        {{ item.label }}
                                        <span v-if="!item.is_public" class="text-xs text-gray-400 ml-1">(nội bộ)</span>
                                    </label>

                                    <!-- Text / Image URL / Color input -->
                                    <template v-if="item.type === 'text' || item.type === 'image' || item.type === 'color'">
                                        <input
                                            v-model="formData[item.key]"
                                            :type="item.type === 'color' ? 'color' : 'text'"
                                            :placeholder="item.label"
                                            :class="[
                                                'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500',
                                                item.type === 'color' ? 'h-10 p-1' : ''
                                            ]"
                                        />
                                        <!-- Image preview -->
                                        <div v-if="item.type === 'image' && formData[item.key]" class="mt-2">
                                            <img :src="formData[item.key]" class="h-12 object-contain rounded border border-gray-200 p-1 bg-gray-50" />
                                        </div>
                                    </template>

                                    <!-- Textarea -->
                                    <textarea
                                        v-else-if="item.type === 'textarea'"
                                        v-model="formData[item.key]"
                                        :placeholder="item.label"
                                        rows="3"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />

                                    <!-- Boolean toggle -->
                                    <label v-else-if="item.type === 'boolean'" class="relative inline-flex items-center cursor-pointer mt-1">
                                        <input
                                            type="checkbox"
                                            :checked="formData[item.key] === '1' || formData[item.key] === true || formData[item.key] === 'true'"
                                            @change="formData[item.key] = $event.target.checked ? '1' : '0'"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                        <span class="ml-3 text-sm text-gray-500">{{ formData[item.key] === '1' || formData[item.key] === true || formData[item.key] === 'true' ? 'Bật' : 'Tắt' }}</span>
                                    </label>

                                    <!-- Number -->
                                    <input
                                        v-else-if="item.type === 'number'"
                                        v-model="formData[item.key]"
                                        type="number"
                                        :placeholder="item.label"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />

                                    <!-- Select -->
                                    <select
                                        v-else-if="item.type === 'select' && item.options"
                                        v-model="formData[item.key]"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />

                                    <p class="text-xs text-gray-400 mt-1">{{ item.key }}</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Save button -->
                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-semibold disabled:opacity-50 transition-colors shadow-sm"
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
