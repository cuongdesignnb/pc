<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    menu: Object,
    locations: Array,
    categories: Array,
});

// --- Menu settings form ---
const settingsForm = useForm({
    name: props.menu.name,
    location: props.menu.location,
    description: props.menu.description || '',
    is_active: props.menu.is_active,
});

function saveSettings() {
    settingsForm.put(`/admin/menus/${props.menu.id}`);
}

// --- Add/Edit item form ---
const showItemModal = ref(false);
const editingItem = ref(null);

const itemForm = useForm({
    parent_id: null,
    title: '',
    url: '',
    type: 'custom',
    category_id: null,
    icon: '',
    badge_text: '',
    badge_color: '',
    css_class: '',
    target: '_self',
    sort_order: 0,
    is_active: true,
    is_mega: false,
    mega_columns: 4,
    description: '',
    image: '',
});

function openAddItem(parentId = null) {
    editingItem.value = null;
    itemForm.reset();
    itemForm.parent_id = parentId;
    itemForm.sort_order = 0;
    showItemModal.value = true;
}

function openEditItem(item) {
    editingItem.value = item;
    itemForm.parent_id = item.parent_id;
    itemForm.title = item.title;
    itemForm.url = item.url || '';
    itemForm.type = item.type;
    itemForm.category_id = item.category_id;
    itemForm.icon = item.icon || '';
    itemForm.badge_text = item.badge_text || '';
    itemForm.badge_color = item.badge_color || '';
    itemForm.css_class = item.css_class || '';
    itemForm.target = item.target;
    itemForm.sort_order = item.sort_order;
    itemForm.is_active = item.is_active;
    itemForm.is_mega = item.is_mega;
    itemForm.mega_columns = item.mega_columns;
    itemForm.description = item.description || '';
    itemForm.image = item.image || '';
    showItemModal.value = true;
}

function submitItem() {
    if (editingItem.value) {
        itemForm.put(`/admin/menus/${props.menu.id}/items/${editingItem.value.id}`, {
            onSuccess: () => { showItemModal.value = false; },
        });
    } else {
        itemForm.post(`/admin/menus/${props.menu.id}/items`, {
            onSuccess: () => { showItemModal.value = false; },
        });
    }
}

function deleteItem(item) {
    if (confirm(`Xóa "${item.title}" và tất cả menu con?`)) {
        router.delete(`/admin/menus/${props.menu.id}/items/${item.id}`);
    }
}

// Flatten categories for select
const flatCategories = computed(() => {
    const result = [];
    function walk(cats, depth = 0) {
        for (const c of cats || []) {
            result.push({ id: c.id, name: '—'.repeat(depth) + ' ' + c.name });
            if (c.children) walk(c.children, depth + 1);
        }
    }
    walk(props.categories);
    return result;
});

// All menu items flat (for parent select)
const flatItems = computed(() => {
    const result = [];
    function walk(items, depth = 0) {
        for (const item of items || []) {
            result.push({ id: item.id, title: '—'.repeat(depth) + ' ' + item.title });
            if (item.children) walk(item.children, depth + 1);
        }
    }
    walk(props.menu.items);
    return result;
});

const badgeColorOptions = [
    { value: 'red', label: 'Đỏ', class: 'bg-red-500' },
    { value: 'green', label: 'Xanh lá', class: 'bg-emerald-500' },
    { value: 'blue', label: 'Xanh dương', class: 'bg-blue-500' },
    { value: 'orange', label: 'Cam', class: 'bg-orange-500' },
    { value: 'purple', label: 'Tím', class: 'bg-purple-500' },
    { value: 'yellow', label: 'Vàng', class: 'bg-yellow-500' },
];

const emojiList = ['🏠','🖥️','💻','🔧','🎧','⚙️','📰','🎮','🏢','🎨','📱','🖌️','⚡','💾','🔲','🔌','📦','❄️','⌨️','🖱️','🛒','🔥','⭐','💎','🎯'];
</script>

<template>
    <AdminLayout title="Chỉnh sửa Menu">
        <div class="flex items-center gap-3 mb-6">
            <Link href="/admin/menus" class="text-gray-400 hover:text-gray-600">← Quay lại</Link>
            <h1 class="text-2xl font-bold text-gray-900">{{ menu.name }}</h1>
            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">{{ menu.location }}</span>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Column 1: Menu Tree -->
            <div class="xl:col-span-2">
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Cấu trúc Menu</h3>
                        <button
                            @click="openAddItem(null)"
                            class="text-sm px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            + Thêm item cấp 1
                        </button>
                    </div>

                    <div class="p-4">
                        <div v-if="menu.items?.length" class="space-y-1">
                            <!-- Level 1 -->
                            <div v-for="item in menu.items" :key="item.id" class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <span class="text-gray-300 cursor-move">⠿</span>
                                    <span v-if="item.icon" class="text-lg">{{ item.icon }}</span>
                                    <span class="font-medium text-gray-900 flex-1">{{ item.title }}</span>
                                    <span v-if="item.badge_text" :class="['text-[10px] font-bold px-1.5 py-0.5 rounded text-white', `bg-${item.badge_color || 'red'}-500`]">
                                        {{ item.badge_text }}
                                    </span>
                                    <span v-if="item.is_mega" class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-medium">MEGA</span>
                                    <span v-if="!item.is_active" class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">Tắt</span>
                                    <span class="text-xs text-gray-400">{{ item.url }}</span>
                                    <button @click="openAddItem(item.id)" class="text-xs text-indigo-600 hover:text-indigo-800 px-1">+Con</button>
                                    <button @click="openEditItem(item)" class="text-xs text-blue-600 hover:text-blue-800 px-1">Sửa</button>
                                    <button @click="deleteItem(item)" class="text-xs text-red-500 hover:text-red-700 px-1">Xóa</button>
                                </div>

                                <!-- Level 2  -->
                                <div v-if="item.children?.length" class="bg-white">
                                    <div v-for="child in item.children" :key="child.id" class="border-t border-gray-100">
                                        <div class="flex items-center gap-2 px-4 py-2.5 pl-10 hover:bg-blue-50/50 transition-colors">
                                            <span class="text-gray-300 cursor-move text-sm">⠿</span>
                                            <span v-if="child.icon" class="text-base">{{ child.icon }}</span>
                                            <span class="font-medium text-sm text-gray-800 flex-1">{{ child.title }}</span>
                                            <span v-if="child.badge_text" :class="['text-[9px] font-bold px-1 py-0.5 rounded text-white', `bg-${child.badge_color || 'red'}-500`]">
                                                {{ child.badge_text }}
                                            </span>
                                            <span class="text-xs text-gray-400">{{ child.url }}</span>
                                            <button @click="openAddItem(child.id)" class="text-xs text-indigo-600 hover:text-indigo-800 px-1">+Con</button>
                                            <button @click="openEditItem(child)" class="text-xs text-blue-600 hover:text-blue-800 px-1">Sửa</button>
                                            <button @click="deleteItem(child)" class="text-xs text-red-500 hover:text-red-700 px-1">Xóa</button>
                                        </div>

                                        <!-- Level 3 -->
                                        <div v-if="child.children?.length">
                                            <div v-for="deep in child.children" :key="deep.id" class="border-t border-gray-50">
                                                <div class="flex items-center gap-2 px-4 py-2 pl-16 hover:bg-indigo-50/50 transition-colors">
                                                    <span class="text-gray-300 cursor-move text-sm">⠿</span>
                                                    <span class="text-sm text-gray-700 flex-1">{{ deep.title }}</span>
                                                    <span class="text-xs text-gray-400">{{ deep.url }}</span>
                                                    <button @click="openAddItem(deep.id)" class="text-xs text-indigo-600 hover:text-indigo-800 px-1">+Con</button>
                                                    <button @click="openEditItem(deep)" class="text-xs text-blue-600 hover:text-blue-800 px-1">Sửa</button>
                                                    <button @click="deleteItem(deep)" class="text-xs text-red-500 hover:text-red-700 px-1">Xóa</button>
                                                </div>

                                                <!-- Level 4 -->
                                                <div v-if="deep.children?.length">
                                                    <div v-for="l4 in deep.children" :key="l4.id" class="border-t border-gray-50">
                                                        <div class="flex items-center gap-2 px-4 py-1.5 pl-24 hover:bg-purple-50/50 transition-colors">
                                                            <span class="text-xs text-gray-600 flex-1">{{ l4.title }}</span>
                                                            <span class="text-xs text-gray-400">{{ l4.url }}</span>
                                                            <button @click="openEditItem(l4)" class="text-xs text-blue-600 hover:text-blue-800 px-1">Sửa</button>
                                                            <button @click="deleteItem(l4)" class="text-xs text-red-500 hover:text-red-700 px-1">Xóa</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-gray-400">
                            <p class="text-lg mb-2">Menu chưa có item nào</p>
                            <button @click="openAddItem(null)" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                + Thêm item đầu tiên
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Column 2: Menu Settings -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Cài đặt Menu</h3>
                    <form @submit.prevent="saveSettings" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tên</label>
                            <input v-model="settingsForm.name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vị trí</label>
                            <select v-model="settingsForm.location" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                <option v-for="loc in locations" :key="loc.value" :value="loc.value">{{ loc.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                            <textarea v-model="settingsForm.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="settingsForm.is_active" id="settings_active" class="rounded border-gray-300 text-indigo-600">
                            <label for="settings_active" class="text-sm text-gray-700">Hoạt động</label>
                        </div>
                        <button type="submit" :disabled="settingsForm.processing" class="w-full py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            Lưu cài đặt
                        </button>
                    </form>
                </div>

                <!-- Quick stats -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Thống kê</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Tổng items</span><span class="font-medium">{{ flatItems.length }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Cấp 1</span><span class="font-medium">{{ menu.items?.length || 0 }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Mega Menu</span><span class="font-medium">{{ menu.items?.filter(i => i.is_mega).length || 0 }}</span></div>
                    </div>
                </div>

                <!-- Quick add from categories -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Thêm nhanh từ danh mục</h3>
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        <div v-for="cat in flatCategories" :key="cat.id" class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-gray-50">
                            <span class="text-sm text-gray-700">{{ cat.name }}</span>
                            <button
                                @click="openAddItem(null); itemForm.title = cat.name.replace(/^—+\s/, ''); itemForm.type = 'category'; itemForm.category_id = cat.id;"
                                class="text-xs text-indigo-600 hover:text-indigo-800"
                            >+ Thêm</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Item Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showItemModal" class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-20 px-4" @click.self="showItemModal = false">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white rounded-t-xl">
                            <h3 class="font-semibold text-gray-900">{{ editingItem ? 'Sửa Menu Item' : 'Thêm Menu Item' }}</h3>
                            <button @click="showItemModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>

                        <form @submit.prevent="submitItem" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề *</label>
                                    <input v-model="itemForm.title" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" placeholder="Tên hiển thị">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Loại</label>
                                    <select v-model="itemForm.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="custom">Tùy chỉnh (URL)</option>
                                        <option value="category">Danh mục</option>
                                        <option value="page">Trang</option>
                                    </select>
                                </div>

                                <div v-if="itemForm.type === 'category'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục</label>
                                    <select v-model="itemForm.category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option :value="null">-- Chọn --</option>
                                        <option v-for="cat in flatCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>

                                <div v-if="itemForm.type !== 'category'" class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                                    <input v-model="itemForm.url" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" placeholder="/categories/cpu">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Menu cha</label>
                                    <select v-model="itemForm.parent_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option :value="null">— Cấp cao nhất —</option>
                                        <option v-for="fi in flatItems" :key="fi.id" :value="fi.id">{{ fi.title }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự</label>
                                    <input v-model.number="itemForm.sort_order" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon (emoji)</label>
                                    <div class="flex gap-2">
                                        <input v-model="itemForm.icon" type="text" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" placeholder="🖥️">
                                    </div>
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        <button
                                            v-for="e in emojiList" :key="e" type="button"
                                            @click="itemForm.icon = e"
                                            class="text-lg w-7 h-7 flex items-center justify-center rounded hover:bg-gray-100"
                                            :class="{ 'bg-indigo-100 ring-1 ring-indigo-300': itemForm.icon === e }"
                                        >{{ e }}</button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Target</label>
                                    <select v-model="itemForm.target" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="_self">Cùng tab</option>
                                        <option value="_blank">Tab mới</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Badge text</label>
                                    <input v-model="itemForm.badge_text" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" placeholder="HOT, Mới, Sale...">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Badge color</label>
                                    <div class="flex gap-2">
                                        <button
                                            v-for="bc in badgeColorOptions" :key="bc.value" type="button"
                                            @click="itemForm.badge_color = bc.value"
                                            :class="[
                                                'w-7 h-7 rounded-full border-2 transition-all',
                                                bc.class,
                                                itemForm.badge_color === bc.value ? 'border-gray-900 scale-110' : 'border-transparent'
                                            ]"
                                            :title="bc.label"
                                        />
                                    </div>
                                </div>

                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả (hiện dưới tiêu đề trong mega menu)</label>
                                    <input v-model="itemForm.description" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" placeholder="Mô tả ngắn gọn">
                                </div>

                                <div class="col-span-2 flex items-center gap-6">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="itemForm.is_active" class="rounded border-gray-300 text-indigo-600">
                                        <span class="text-sm text-gray-700">Hoạt động</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="itemForm.is_mega" class="rounded border-gray-300 text-purple-600">
                                        <span class="text-sm text-gray-700">Mega Menu</span>
                                    </label>
                                    <div v-if="itemForm.is_mega" class="flex items-center gap-2">
                                        <label class="text-sm text-gray-700">Số cột:</label>
                                        <input v-model.number="itemForm.mega_columns" type="number" min="1" max="6" class="w-16 px-2 py-1 border border-gray-300 rounded-lg text-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                                <button
                                    type="submit"
                                    :disabled="itemForm.processing"
                                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ editingItem ? 'Cập nhật' : 'Thêm item' }}
                                </button>
                                <button type="button" @click="showItemModal = false" class="text-sm text-gray-500 hover:text-gray-700">Hủy</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AdminLayout>
</template>
