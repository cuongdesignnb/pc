<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const currentUrl = computed(() => page.props.ziggy?.location || page.url);

const sidebarOpen = ref(true);

// Grouped navigation
const navGroups = [
    {
        items: [
            { name: 'Dashboard', href: '/admin', icon: 'dashboard', exact: true },
        ],
    },
    {
        label: 'Bán hàng',
        key: 'sales',
        icon: 'cart',
        items: [
            { name: 'Đơn hàng', href: '/admin/orders', icon: 'order' },
            { name: 'Mã giảm giá', href: '/admin/coupons', icon: 'coupon' },
        ],
    },
    {
        label: 'Sản phẩm',
        key: 'catalog',
        icon: 'box',
        items: [
            { name: 'Tất cả sản phẩm', href: '/admin/products', icon: 'product' },
            { name: 'Danh mục', href: '/admin/categories', icon: 'folder' },
            { name: 'Thương hiệu', href: '/admin/brands', icon: 'tag' },
            { name: 'Loại linh kiện', href: '/admin/component-types', icon: 'cpu' },
            { name: 'Tương thích', href: '/admin/compatibility', icon: 'compat' },
        ],
    },
    {
        label: 'Nội dung',
        key: 'content',
        icon: 'content',
        items: [
            { name: 'Bài viết', href: '/admin/posts', icon: 'post' },
            { name: 'Danh mục bài viết', href: '/admin/post-categories', icon: 'category' },
            { name: 'Trang tĩnh', href: '/admin/pages', icon: 'page' },
            { name: 'Banner', href: '/admin/banners', icon: 'banner' },
            { name: 'Thư viện Media', href: '/admin/media', icon: 'media' },
        ],
    },
    {
        label: 'Khách hàng',
        key: 'customers',
        icon: 'users',
        items: [
            { name: 'Danh sách KH', href: '/admin/customers', icon: 'user' },
            { name: 'Đánh giá', href: '/admin/reviews', icon: 'star' },
        ],
    },
    {
        label: 'Giao diện',
        key: 'appearance',
        icon: 'palette',
        items: [
            { name: 'Menu', href: '/admin/menus', icon: 'menu' },
            { name: 'Cài đặt', href: '/admin/settings', icon: 'settings' },
        ],
    },
];

// Track which groups are expanded — auto-expand if any child is active
function isGroupActive(group) {
    return group.items.some(item => isActive(item));
}

// Initialize expanded state
const expanded = reactive({});
navGroups.forEach(g => {
    if (g.key) {
        expanded[g.key] = isGroupActive(g);
    }
});

function toggleGroup(key) {
    expanded[key] = !expanded[key];
}

function isActive(item) {
    const url = page.url;
    if (item.exact) return url === item.href || url === item.href + '/';
    return url.startsWith(item.href);
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-60 bg-white border-r border-gray-200 transform transition-transform duration-300 ease-in-out flex flex-col',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo -->
            <div class="flex items-center gap-2.5 h-16 px-5 border-b border-gray-200 flex-shrink-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <span class="text-base font-bold text-gray-900">PC Shop</span>
                <span class="text-[10px] font-medium text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded ml-auto">Admin</span>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-0.5">
                <template v-for="(group, gi) in navGroups" :key="gi">
                    <!-- Standalone items (Dashboard) -->
                    <template v-if="!group.label">
                        <Link
                            v-for="item in group.items"
                            :key="item.name"
                            :href="item.href"
                            :class="isActive(item) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-colors"
                        >
                            <!-- Dashboard icon -->
                            <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>
                            {{ item.name }}
                        </Link>
                    </template>

                    <!-- Grouped items -->
                    <template v-else>
                        <div class="pt-2 first:pt-0">
                            <button
                                @click="toggleGroup(group.key)"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-[11px] font-semibold uppercase tracking-wider text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors"
                            >
                                <span>{{ group.label }}</span>
                                <svg :class="expanded[group.key] ? 'rotate-90' : ''" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>

                            <div v-show="expanded[group.key]" class="mt-0.5 space-y-0.5">
                                <Link
                                    v-for="item in group.items"
                                    :key="item.name"
                                    :href="item.href"
                                    :class="isActive(item) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                                    class="flex items-center gap-3 pl-5 pr-3 py-2 rounded-lg text-[13px] transition-colors"
                                >
                                    <!-- SVG icons per type -->
                                    <!-- Order -->
                                    <svg v-if="item.icon === 'order'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                    <!-- Coupon -->
                                    <svg v-else-if="item.icon === 'coupon'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    <!-- Product -->
                                    <svg v-else-if="item.icon === 'product'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <!-- Folder -->
                                    <svg v-else-if="item.icon === 'folder'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    <!-- Tag -->
                                    <svg v-else-if="item.icon === 'tag'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                                    <!-- CPU -->
                                    <svg v-else-if="item.icon === 'cpu'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                    <!-- Compat -->
                                    <svg v-else-if="item.icon === 'compat'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <!-- Post -->
                                    <svg v-else-if="item.icon === 'post'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                    <!-- Page -->
                                    <svg v-else-if="item.icon === 'page'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <!-- Banner -->
                                    <svg v-else-if="item.icon === 'banner'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <!-- Media -->
                                    <svg v-else-if="item.icon === 'media'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    <!-- User -->
                                    <svg v-else-if="item.icon === 'user'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <!-- Star -->
                                    <svg v-else-if="item.icon === 'star'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                    <!-- Menu -->
                                    <svg v-else-if="item.icon === 'menu'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                    <!-- Settings -->
                                    <svg v-else-if="item.icon === 'settings'" class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <!-- Fallback -->
                                    <svg v-else class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>

                                    {{ item.name }}
                                </Link>
                            </div>
                        </div>
                    </template>
                </template>
            </nav>

            <!-- Footer user -->
            <div class="border-t border-gray-200 p-3 flex-shrink-0">
                <div class="flex items-center gap-3 px-2 py-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                        {{ user?.name?.charAt(0) || 'A' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ user?.name || 'Admin' }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ user?.email }}</p>
                    </div>
                    <Link href="/logout" method="post" as="button" class="p-1.5 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50" title="Đăng xuất">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div :class="['transition-all duration-300 min-h-screen flex flex-col', sidebarOpen ? 'ml-60' : 'ml-0']">
            <!-- Top bar -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
                <div class="flex items-center justify-between h-14 px-6">
                    <div class="flex items-center gap-3">
                        <button
                            @click="sidebarOpen = !sidebarOpen"
                            class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <h2 class="text-[15px] font-semibold text-gray-900">{{ title }}</h2>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
