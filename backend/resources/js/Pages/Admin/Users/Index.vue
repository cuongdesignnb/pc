<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    users: Object,
    filters: Object,
});

const page = usePage();
const can = (perm) => {
    const perms = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('super-admin') || perms.includes(perm);
};

const search = ref(props.filters?.search || '');

function applyFilter() {
    router.get('/admin/users', { search: search.value || undefined }, {
        preserveState: true,
        replace: true,
    });
}

function deleteUser(user) {
    if (confirm(`Bạn có chắc muốn xóa tài khoản "${user.name}"?`)) {
        router.delete(`/admin/users/${user.id}`);
    }
}

const roleLabels = {
    'super-admin': { text: 'Super Admin', class: 'badge-super' },
    'admin': { text: 'Admin', class: 'badge-admin' },
    'staff': { text: 'Nhân viên', class: 'badge-staff' },
};
</script>

<template>
    <AdminLayout title="Quản lý nhân viên">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white">Quản lý nhân viên</h1>
                <p class="text-sm text-slate-500 mt-1">Quản lý tài khoản admin & nhân viên</p>
            </div>
            <Link
                v-if="can('users.create')"
                href="/admin/users/create"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-sm font-semibold rounded-lg shadow-lg shadow-cyan-500/20 hover:shadow-cyan-500/40 transition-all hover:-translate-y-0.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Thêm nhân viên
            </Link>
        </div>

        <!-- Search -->
        <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-4 mb-6">
            <form @submit.prevent="applyFilter" class="flex gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm theo tên, email..."
                        class="w-full pl-9 pr-4 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-300 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50"
                    />
                </div>
                <button type="submit" class="px-4 py-2.5 bg-slate-800 text-slate-300 text-sm rounded-lg border border-slate-700/50 hover:bg-slate-700 transition-colors">
                    Tìm kiếm
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-slate-900 rounded-xl border border-slate-800/60 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-800/40">
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Nhân viên</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Vai trò hệ thống</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Vai trò phân quyền</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/30">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center text-xs font-bold text-white ring-2 ring-violet-500/20">
                                        {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-200">{{ user.name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ user.phone || '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-400">{{ user.email }}</td>
                            <td class="px-5 py-3">
                                <span :class="['inline-flex px-2.5 py-1 text-[11px] font-medium rounded-full border',
                                    user.role === 'admin' ? 'bg-amber-500/15 text-amber-400 border-amber-500/20' : 'bg-blue-500/15 text-blue-400 border-blue-500/20'
                                ]">
                                    {{ user.role === 'admin' ? 'Admin' : 'Nhân viên' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        :class="[
                                            'inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full border',
                                            role.name === 'super-admin' ? 'bg-red-500/15 text-red-400 border-red-500/20' :
                                            role.name === 'admin' ? 'bg-amber-500/15 text-amber-400 border-amber-500/20' :
                                            'bg-cyan-500/15 text-cyan-400 border-cyan-500/20'
                                        ]"
                                    >
                                        {{ role.name }}
                                    </span>
                                    <span v-if="!user.roles?.length" class="text-[11px] text-slate-600">Chưa gán</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        v-if="can('users.edit')"
                                        :href="`/admin/users/${user.id}/edit`"
                                        class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-800 rounded-lg transition-colors"
                                        title="Sửa"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </Link>
                                    <button
                                        v-if="can('users.delete')"
                                        @click="deleteUser(user)"
                                        class="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors"
                                        title="Xóa"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data?.length">
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-600">Chưa có nhân viên nào</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="border-t border-slate-800/40 px-5 py-3 flex items-center justify-between">
                <p class="text-xs text-slate-500">Hiển thị {{ users.from }}–{{ users.to }} / {{ users.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url || ''"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg transition-colors',
                            link.active ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:bg-slate-800',
                            !link.url ? 'opacity-30 pointer-events-none' : ''
                        ]"
                        v-html="link.label"
                        preserve-state
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
