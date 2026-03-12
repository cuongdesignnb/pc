<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    menus: Array,
});

const locationLabels = {
    header: 'Header',
    footer: 'Footer',
    sidebar: 'Sidebar',
    mobile: 'Mobile',
};
</script>

<template>
    <AdminLayout title="Quản lý Menu">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Quản lý Menu</h1>
                <p class="text-sm text-gray-500 mt-1">Tạo và quản lý các menu cho website</p>
            </div>
            <Link
                href="/admin/menus/create"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
            >
                + Tạo Menu mới
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên Menu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vị trí</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="menu in menus" :key="menu.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ menu.name }}</div>
                            <div class="text-xs text-gray-400">{{ menu.slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ locationLabels[menu.location] || menu.location }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ menu.all_items_count }} items
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                    menu.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                                ]"
                            >
                                {{ menu.is_active ? 'Hoạt động' : 'Tắt' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <Link
                                :href="`/admin/menus/${menu.id}/edit`"
                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                            >
                                Chỉnh sửa
                            </Link>
                            <Link
                                :href="`/admin/menus/${menu.id}`"
                                method="delete"
                                as="button"
                                class="text-red-600 hover:text-red-800 text-sm font-medium"
                                :confirm="'Bạn có chắc muốn xóa menu này?'"
                            >
                                Xóa
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!menus?.length">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                            Chưa có menu nào. Hãy tạo menu đầu tiên!
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
