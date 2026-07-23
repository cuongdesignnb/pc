<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ToastNotification from '@/Components/ToastNotification.vue';

const props = defineProps({
    title: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const currentUrl = computed(() => page.props.ziggy?.location || page.url);

// Permission check helper
const can = (perm) => {
    const perms = user.value?.permissions || [];
    const roles = user.value?.roles || [];
    return roles.includes('super-admin') || perms.includes(perm);
};

const sidebarOpen = ref(true);
const sidebarCollapsed = ref(false);
const searchQuery = ref('');

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
        items: [
            { name: 'Đơn hàng', href: '/admin/orders', icon: 'order' },
            { name: 'Mã giảm giá', href: '/admin/coupons', icon: 'coupon' },
        ],
    },
    {
        label: 'Sản phẩm',
        key: 'catalog',
        items: [
            { name: 'Tất cả sản phẩm', href: '/admin/products', icon: 'product' },
            { name: 'Danh mục', href: '/admin/categories', icon: 'folder' },
            { name: 'Thương hiệu', href: '/admin/brands', icon: 'tag' },
            { name: 'Bộ lọc', href: '/admin/filters', icon: 'filter' },
            { name: 'Loại linh kiện', href: '/admin/component-types', icon: 'cpu' },
            { name: 'Tương thích', href: '/admin/compatibility', icon: 'compat' },
        ],
    },
    {
        label: 'Nội dung',
        key: 'content',
        items: [
            { name: 'Bài viết', href: '/admin/posts', icon: 'post' },
            { name: 'DM bài viết', href: '/admin/post-categories', icon: 'category' },
            { name: 'AI Bài viết', href: '/admin/ai-articles', icon: 'ai' },
            { name: 'Trang tĩnh', href: '/admin/pages', icon: 'page' },
            { name: 'Banner', href: '/admin/banners', icon: 'banner' },
            { name: 'Thư viện Media', href: '/admin/media', icon: 'media' },
        ],
    },
    {
        label: 'Khách hàng',
        key: 'customers',
        items: [
            { name: 'Danh sách KH', href: '/admin/customers', icon: 'user' },
            { name: 'Đánh giá', href: '/admin/reviews', icon: 'star' },
        ],
    },
    {
        label: 'Hệ thống',
        key: 'system',
        items: [
            { name: 'Menu', href: '/admin/menus', icon: 'menu' },
            { name: 'Cài đặt', href: '/admin/settings', icon: 'settings' },
            { name: 'Tích hợp KIOT', href: '/admin/integrations/kiot', icon: 'compat', perm: 'settings.view' },
            { name: 'Nhân viên', href: '/admin/users', icon: 'user', perm: 'users.view' },
            { name: 'Vai trò', href: '/admin/roles', icon: 'shield', perm: 'roles.view' },
        ],
    },
];

// Track which groups are expanded
function isGroupActive(group) {
    return group.items.some(item => isActive(item));
}

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

// Icon SVG paths
const icons = {
    dashboard: 'M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z',
    order: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    coupon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
    product: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    folder: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    tag: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z',
    filter: 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
    cpu: 'M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z',
    compat: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    post: 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
    category: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    ai: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
    page: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    banner: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    media: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    user: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    star: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
    menu: 'M4 6h16M4 12h16M4 18h7',
    settings: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
};
</script>

<template>
    <div class="min-h-screen bg-slate-950">
        <ToastNotification />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col transform transition-all duration-300 ease-in-out border-r border-slate-800/80',
                sidebarOpen ? (sidebarCollapsed ? 'w-[68px]' : 'w-60') : '-translate-x-full w-60',
            ]"
            style="background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%)"
        >
            <!-- Logo -->
            <div class="flex items-center h-14 px-4 border-b border-slate-800/80 flex-shrink-0">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-cyan-500/20">
                    <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <template v-if="!sidebarCollapsed">
                    <span class="ml-3 text-sm font-bold text-white tracking-wide">PC Shop</span>
                    <span class="ml-auto text-[9px] font-semibold text-cyan-400 bg-cyan-400/10 px-1.5 py-0.5 rounded border border-cyan-400/20">ADMIN</span>
                </template>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-0.5 scrollbar-thin">
                <template v-for="(group, gi) in navGroups" :key="gi">
                    <!-- Standalone (Dashboard) -->
                    <template v-if="!group.label">
                        <Link
                            v-for="item in group.items"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                isActive(item)
                                    ? 'bg-cyan-500/10 text-cyan-400 border-l-2 border-cyan-400'
                                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 border-l-2 border-transparent',
                            ]"
                            class="flex items-center gap-3 px-3 py-2 rounded-r-lg text-[13px] font-medium transition-all duration-150"
                        >
                            <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="icons[item.icon] || ''" /></svg>
                            <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                        </Link>
                    </template>

                    <!-- Grouped items -->
                    <template v-else>
                        <div :class="gi > 1 ? 'pt-3' : 'pt-2'">
                            <button
                                v-if="!sidebarCollapsed"
                                @click="toggleGroup(group.key)"
                                class="w-full flex items-center justify-between px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500 hover:text-slate-300 transition-colors"
                            >
                                <span>{{ group.label }}</span>
                                <svg :class="expanded[group.key] ? 'rotate-90' : ''" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </button>

                            <div v-show="sidebarCollapsed || expanded[group.key]" class="mt-0.5 space-y-0.5">
                                <template v-for="item in group.items" :key="item.name">
                                <Link
                                    v-if="!item.perm || can(item.perm)"
                                    :href="item.href"
                                    :class="[
                                        isActive(item)
                                            ? 'bg-cyan-500/10 text-cyan-400 border-l-2 border-cyan-400'
                                            : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 border-l-2 border-transparent',
                                    ]"
                                    class="flex items-center gap-3 px-3 py-2 rounded-r-lg text-[13px] transition-all duration-150"
                                    :title="sidebarCollapsed ? item.name : ''"
                                >
                                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="icons[item.icon] || ''" /></svg>
                                    <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                                </Link>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>
            </nav>

            <!-- Footer user -->
            <div class="border-t border-slate-800/80 p-2.5 flex-shrink-0">
                <div class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-slate-800/40 transition-colors">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center text-[11px] font-bold text-white shrink-0 ring-2 ring-violet-500/30">
                        {{ user?.name?.charAt(0)?.toUpperCase() || 'A' }}
                    </div>
                    <template v-if="!sidebarCollapsed">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-200 truncate">{{ user?.name || 'Admin' }}</p>
                            <p class="text-[10px] text-slate-500 truncate">{{ user?.email }}</p>
                        </div>
                        <Link href="/admin/logout" method="post" as="button" class="p-1.5 text-slate-500 hover:text-red-400 transition-colors rounded" title="Đăng xuất">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </Link>
                    </template>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div :class="['transition-all duration-300 min-h-screen flex flex-col', sidebarOpen ? (sidebarCollapsed ? 'ml-[68px]' : 'ml-60') : 'ml-0']">
            <!-- Top bar -->
            <header class="bg-slate-900/80 backdrop-blur-xl border-b border-slate-800/60 sticky top-0 z-40">
                <div class="flex items-center justify-between h-14 px-6">
                    <div class="flex items-center gap-3">
                        <button
                            @click="sidebarOpen = !sidebarOpen"
                            class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <button
                            v-if="sidebarOpen"
                            @click="sidebarCollapsed = !sidebarCollapsed"
                            class="p-2 text-slate-500 hover:text-white hover:bg-slate-800 rounded-lg transition-colors hidden lg:block"
                            :title="sidebarCollapsed ? 'Mở rộng' : 'Thu gọn'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="sidebarCollapsed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M19 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <div class="h-5 w-px bg-slate-800 mx-1 hidden sm:block"></div>
                        <h2 class="text-sm font-semibold text-slate-200">{{ title }}</h2>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Quick search -->
                        <div class="relative hidden md:block">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Tìm kiếm..."
                                class="w-56 pl-9 pr-3 py-2 bg-slate-800/60 border border-slate-700/50 rounded-lg text-xs text-slate-300 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50 focus:border-cyan-500/50 transition-colors"
                            />
                        </div>

                        <!-- Visit site -->
                        <a href="/" target="_blank" class="p-2 text-slate-500 hover:text-cyan-400 rounded-lg hover:bg-slate-800 transition-colors" title="Xem website">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
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

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.3);
    border-radius: 9999px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.5);
}
</style>

<style>
/* ===== GLOBAL ADMIN DARK THEME ===== */

/* Root: ALL text defaults to light */
.min-h-screen.bg-slate-950 {
    color: #e2e8f0;
}

/* Headings */
.min-h-screen.bg-slate-950 h1,
.min-h-screen.bg-slate-950 h2,
.min-h-screen.bg-slate-950 h3,
.min-h-screen.bg-slate-950 h4,
.min-h-screen.bg-slate-950 h5,
.min-h-screen.bg-slate-950 h6 {
    color: #f1f5f9;
}

/* Paragraphs, spans, divs inherit */
.min-h-screen.bg-slate-950 p,
.min-h-screen.bg-slate-950 span,
.min-h-screen.bg-slate-950 div,
.min-h-screen.bg-slate-950 li,
.min-h-screen.bg-slate-950 td,
.min-h-screen.bg-slate-950 th {
    color: inherit;
}

/* Labels */
.min-h-screen.bg-slate-950 label {
    color: #cbd5e1;
}

/* Links */
.min-h-screen.bg-slate-950 a {
    color: #67e8f9;
}
.min-h-screen.bg-slate-950 a:hover {
    color: #a5f3fc;
}

/* Form inputs */
.min-h-screen.bg-slate-950 input[type="text"],
.min-h-screen.bg-slate-950 input[type="number"],
.min-h-screen.bg-slate-950 input[type="email"],
.min-h-screen.bg-slate-950 input[type="password"],
.min-h-screen.bg-slate-950 input[type="url"],
.min-h-screen.bg-slate-950 input[type="tel"],
.min-h-screen.bg-slate-950 input[type="search"],
.min-h-screen.bg-slate-950 input[type="date"],
.min-h-screen.bg-slate-950 input[type="datetime-local"],
.min-h-screen.bg-slate-950 input[type="time"],
.min-h-screen.bg-slate-950 input:not([type]),
.min-h-screen.bg-slate-950 textarea,
.min-h-screen.bg-slate-950 select {
    background-color: rgba(30, 41, 59, 0.6) !important;
    color: #e2e8f0 !important;
    border-color: rgba(51, 65, 85, 0.5) !important;
}
.min-h-screen.bg-slate-950 input::placeholder,
.min-h-screen.bg-slate-950 textarea::placeholder {
    color: #64748b !important;
}
.min-h-screen.bg-slate-950 input:focus,
.min-h-screen.bg-slate-950 textarea:focus,
.min-h-screen.bg-slate-950 select:focus {
    border-color: rgba(6, 182, 212, 0.5) !important;
    box-shadow: 0 0 0 1px rgba(6, 182, 212, 0.25) !important;
    outline: none !important;
}
.min-h-screen.bg-slate-950 select option {
    background-color: #1e293b;
    color: #e2e8f0;
}

/* Checkbox & Radio */
.min-h-screen.bg-slate-950 input[type="checkbox"],
.min-h-screen.bg-slate-950 input[type="radio"] {
    background-color: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(71, 85, 105, 0.6) !important;
    color: #06b6d4 !important;
}
.min-h-screen.bg-slate-950 input[type="checkbox"]:checked,
.min-h-screen.bg-slate-950 input[type="radio"]:checked {
    background-color: #06b6d4 !important;
    border-color: #06b6d4 !important;
}

/* Tables */
.min-h-screen.bg-slate-950 table {
    color: #e2e8f0;
}
.min-h-screen.bg-slate-950 thead th {
    color: #94a3b8 !important;
}
.min-h-screen.bg-slate-950 tbody td {
    color: #cbd5e1;
}
.min-h-screen.bg-slate-950 tr:hover td {
    color: #e2e8f0;
}

/* Buttons - keep specific Tailwind colors but fix text */
.min-h-screen.bg-slate-950 button {
    color: inherit;
}

/* Rich editor / TipTap */
.min-h-screen.bg-slate-950 .tiptap,
.min-h-screen.bg-slate-950 .ProseMirror {
    background-color: rgba(30, 41, 59, 0.6) !important;
    color: #e2e8f0 !important;
    border-color: rgba(51, 65, 85, 0.5) !important;
}
.min-h-screen.bg-slate-950 .tiptap .is-editor-empty:first-child::before {
    color: #64748b !important;
}
/* Editor toolbar */
.min-h-screen.bg-slate-950 .editor-toolbar,
.min-h-screen.bg-slate-950 [class*="toolbar"],
.min-h-screen.bg-slate-950 [class*="menu-bar"] {
    background-color: rgba(15, 23, 42, 0.8) !important;
    border-color: rgba(51, 65, 85, 0.5) !important;
}
.min-h-screen.bg-slate-950 .editor-toolbar button,
.min-h-screen.bg-slate-950 [class*="toolbar"] button,
.min-h-screen.bg-slate-950 [class*="menu-bar"] button {
    color: #94a3b8 !important;
}
.min-h-screen.bg-slate-950 .editor-toolbar button:hover,
.min-h-screen.bg-slate-950 [class*="toolbar"] button:hover,
.min-h-screen.bg-slate-950 [class*="menu-bar"] button:hover {
    color: #e2e8f0 !important;
    background-color: rgba(51, 65, 85, 0.5) !important;
}
.min-h-screen.bg-slate-950 .editor-toolbar button.is-active,
.min-h-screen.bg-slate-950 [class*="toolbar"] button.is-active,
.min-h-screen.bg-slate-950 [class*="menu-bar"] button.is-active {
    color: #06b6d4 !important;
    background-color: rgba(6, 182, 212, 0.1) !important;
}

/* File input */
.min-h-screen.bg-slate-950 input[type="file"] {
    color: #94a3b8;
}
.min-h-screen.bg-slate-950 input[type="file"]::file-selector-button {
    background-color: #334155;
    color: #e2e8f0;
    border: 1px solid rgba(51, 65, 85, 0.5);
    border-radius: 0.375rem;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
}

/* Pagination */
.min-h-screen.bg-slate-950 nav[aria-label*="Pagination"] span,
.min-h-screen.bg-slate-950 nav[aria-label*="Pagination"] a,
.min-h-screen.bg-slate-950 nav[aria-label*="Pagination"] button {
    color: #94a3b8;
}

/* Scrollbar for main content */
.min-h-screen.bg-slate-950 ::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.min-h-screen.bg-slate-950 ::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.5);
}
.min-h-screen.bg-slate-950 ::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.4);
    border-radius: 9999px;
}
/* Badge/Label overrides - convert light bg to dark */
.min-h-screen.bg-slate-950 .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-amber-100 {
    background-color: rgba(245, 158, 11, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-red-50 {
    background-color: rgba(239, 68, 68, 0.1) !important;
}
.min-h-screen.bg-slate-950 .bg-indigo-100 {
    background-color: rgba(99, 102, 241, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-purple-100 {
    background-color: rgba(168, 85, 247, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-orange-100 {
    background-color: rgba(249, 115, 22, 0.15) !important;
}
.min-h-screen.bg-slate-950 .bg-pink-100 {
    background-color: rgba(236, 72, 153, 0.15) !important;
}
/* Fix text colors on badges */
.min-h-screen.bg-slate-950 .text-green-700,
.min-h-screen.bg-slate-950 .text-green-800 {
    color: #34d399 !important;
}
.min-h-screen.bg-slate-950 .text-blue-700,
.min-h-screen.bg-slate-950 .text-blue-800 {
    color: #60a5fa !important;
}
.min-h-screen.bg-slate-950 .text-amber-700,
.min-h-screen.bg-slate-950 .text-amber-800 {
    color: #fbbf24 !important;
}
.min-h-screen.bg-slate-950 .text-yellow-700,
.min-h-screen.bg-slate-950 .text-yellow-800 {
    color: #facc15 !important;
}
.min-h-screen.bg-slate-950 .text-red-700,
.min-h-screen.bg-slate-950 .text-red-800 {
    color: #f87171 !important;
}
.min-h-screen.bg-slate-950 .text-indigo-800 {
    color: #818cf8 !important;
}
.min-h-screen.bg-slate-950 .text-purple-700,
.min-h-screen.bg-slate-950 .text-purple-800 {
    color: #c084fc !important;
}
/* Fix border colors on alerts/badges */
.min-h-screen.bg-slate-950 .border-green-200 {
    border-color: rgba(16, 185, 129, 0.25) !important;
}
.min-h-screen.bg-slate-950 .border-red-200 {
    border-color: rgba(239, 68, 68, 0.25) !important;
}
.min-h-screen.bg-slate-950 .border-yellow-200 {
    border-color: rgba(234, 179, 8, 0.25) !important;
}
.min-h-screen.bg-slate-950 .border-blue-200 {
    border-color: rgba(59, 130, 246, 0.25) !important;
}
</style>
