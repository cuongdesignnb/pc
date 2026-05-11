<script setup>
import { ref, computed } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    roles: Array,
    permissions: Object, // grouped by module
});

const page = usePage();
const can = (perm) => {
    const perms = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('super-admin') || perms.includes(perm);
};

// Module labels (Vietnamese)
const moduleLabels = {
    'dashboard': 'Dashboard',
    'products': 'Sản phẩm',
    'categories': 'Danh mục',
    'brands': 'Thương hiệu',
    'filters': 'Bộ lọc',
    'component-types': 'Loại linh kiện',
    'compatibility': 'Tương thích',
    'orders': 'Đơn hàng',
    'coupons': 'Mã giảm giá',
    'posts': 'Bài viết',
    'post-categories': 'DM bài viết',
    'ai-articles': 'AI Bài viết',
    'pages': 'Trang tĩnh',
    'banners': 'Banner',
    'media': 'Thư viện Media',
    'customers': 'Khách hàng',
    'reviews': 'Đánh giá',
    'menus': 'Menu',
    'settings': 'Cài đặt',
    'users': 'Quản lý nhân viên',
    'roles': 'Quản lý vai trò',
};

const actionLabels = {
    'view': 'Xem',
    'create': 'Thêm',
    'edit': 'Sửa',
    'delete': 'Xóa',
    'import': 'Nhập',
    'export': 'Xuất',
    'upload': 'Upload',
};

// ─── New role form ───
const showNewRoleForm = ref(false);
const newRoleForm = useForm({
    name: '',
    permissions: [],
});

function createRole() {
    newRoleForm.post('/admin/roles', {
        preserveScroll: true,
        onSuccess: () => {
            showNewRoleForm.value = false;
            newRoleForm.reset();
        },
    });
}

// ─── Edit role ───
const editingRole = ref(null);
const editForm = useForm({
    name: '',
    permissions: [],
});

function startEdit(role) {
    editingRole.value = role.id;
    editForm.name = role.name;
    editForm.permissions = role.permissions?.map(p => p.id) || [];
}

function cancelEdit() {
    editingRole.value = null;
    editForm.reset();
}

function updateRole(role) {
    editForm.put(`/admin/roles/${role.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingRole.value = null;
        },
    });
}

function deleteRole(role) {
    if (confirm(`Bạn có chắc muốn xóa vai trò "${role.name}"?`)) {
        router.delete(`/admin/roles/${role.id}`, { preserveScroll: true });
    }
}

function togglePermission(form, permId) {
    const idx = form.permissions.indexOf(permId);
    if (idx >= 0) {
        form.permissions.splice(idx, 1);
    } else {
        form.permissions.push(permId);
    }
}

function toggleAllModule(form, perms) {
    const permIds = perms.map(p => p.id);
    const allChecked = permIds.every(id => form.permissions.includes(id));
    if (allChecked) {
        form.permissions = form.permissions.filter(id => !permIds.includes(id));
    } else {
        const newPerms = permIds.filter(id => !form.permissions.includes(id));
        form.permissions.push(...newPerms);
    }
}

function isModuleAllChecked(form, perms) {
    return perms.every(p => form.permissions.includes(p.id));
}
</script>

<template>
    <AdminLayout title="Quản lý vai trò">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-white">Quản lý vai trò & phân quyền</h1>
                <p class="text-sm text-slate-500 mt-1">Thiết lập vai trò và phân quyền chi tiết cho nhân viên</p>
            </div>
            <button
                v-if="can('roles.create')"
                @click="showNewRoleForm = !showNewRoleForm"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-sm font-semibold rounded-lg shadow-lg shadow-cyan-500/20 hover:shadow-cyan-500/40 transition-all hover:-translate-y-0.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Thêm vai trò
            </button>
        </div>

        <!-- New Role Form -->
        <div v-if="showNewRoleForm" class="bg-slate-900 rounded-xl border border-cyan-500/30 p-6 mb-6 animate-slideDown">
            <h3 class="text-sm font-semibold text-white mb-4">Tạo vai trò mới</h3>
            <form @submit.prevent="createRole">
                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Tên vai trò *</label>
                    <input v-model="newRoleForm.name" type="text" class="w-full max-w-sm px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="vd: editor, marketing..." required />
                    <p v-if="newRoleForm.errors.name" class="mt-1 text-xs text-red-400">{{ newRoleForm.errors.name }}</p>
                </div>

                <!-- Permissions grid -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-400 mb-3">Phân quyền</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        <div v-for="(perms, module) in permissions" :key="module" class="bg-slate-800/40 rounded-lg border border-slate-700/30 p-3">
                            <label class="flex items-center gap-2 mb-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="isModuleAllChecked(newRoleForm, perms)"
                                    @change="toggleAllModule(newRoleForm, perms)"
                                    class="w-4 h-4 rounded border-slate-600 text-cyan-500"
                                />
                                <span class="text-xs font-semibold text-slate-300">{{ moduleLabels[module] || module }}</span>
                            </label>
                            <div class="pl-6 space-y-1">
                                <label v-for="perm in perms" :key="perm.id" class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="newRoleForm.permissions.includes(perm.id)"
                                        @change="togglePermission(newRoleForm, perm.id)"
                                        class="w-3.5 h-3.5 rounded border-slate-600 text-cyan-500"
                                    />
                                    <span class="text-[11px] text-slate-400">{{ actionLabels[perm.name.split('.')[1]] || perm.name.split('.')[1] }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" :disabled="newRoleForm.processing" class="px-4 py-2 bg-cyan-500 text-white text-sm font-medium rounded-lg hover:bg-cyan-400 transition-colors disabled:opacity-50">
                        Tạo vai trò
                    </button>
                    <button type="button" @click="showNewRoleForm = false" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                        Hủy
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing Roles -->
        <div class="space-y-4">
            <div v-for="role in roles" :key="role.id" class="bg-slate-900 rounded-xl border border-slate-800/60 overflow-hidden">
                <!-- Role header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800/40">
                    <div class="flex items-center gap-3">
                        <div :class="[
                            'w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold',
                            role.name === 'super-admin' ? 'bg-red-500/15 text-red-400 border border-red-500/20' :
                            role.name === 'admin' ? 'bg-amber-500/15 text-amber-400 border border-amber-500/20' :
                            'bg-cyan-500/15 text-cyan-400 border border-cyan-500/20'
                        ]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <template v-if="editingRole === role.id">
                                <input v-model="editForm.name" type="text" class="px-2 py-1 bg-slate-800 border border-slate-600 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500/50" />
                            </template>
                            <template v-else>
                                <h3 class="text-sm font-semibold text-white">{{ role.name }}</h3>
                                <p class="text-[11px] text-slate-500">{{ role.permissions?.length || 0 }} quyền · {{ role.users_count || 0 }} người dùng</p>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-if="editingRole === role.id">
                            <button @click="updateRole(role)" class="px-3 py-1.5 bg-cyan-500 text-white text-xs font-medium rounded-lg hover:bg-cyan-400 transition-colors">Lưu</button>
                            <button @click="cancelEdit" class="px-3 py-1.5 text-xs text-slate-400 hover:text-white transition-colors">Hủy</button>
                        </template>
                        <template v-else>
                            <button
                                v-if="can('roles.edit') && role.name !== 'super-admin'"
                                @click="startEdit(role)"
                                class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-800 rounded-lg transition-colors"
                                title="Sửa"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button
                                v-if="can('roles.delete') && !['super-admin', 'admin', 'staff'].includes(role.name)"
                                @click="deleteRole(role)"
                                class="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition-colors"
                                title="Xóa"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Permissions for editing -->
                <div v-if="editingRole === role.id" class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        <div v-for="(perms, module) in permissions" :key="module" class="bg-slate-800/40 rounded-lg border border-slate-700/30 p-3">
                            <label class="flex items-center gap-2 mb-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="isModuleAllChecked(editForm, perms)"
                                    @change="toggleAllModule(editForm, perms)"
                                    class="w-4 h-4 rounded border-slate-600 text-cyan-500"
                                />
                                <span class="text-xs font-semibold text-slate-300">{{ moduleLabels[module] || module }}</span>
                            </label>
                            <div class="pl-6 space-y-1">
                                <label v-for="perm in perms" :key="perm.id" class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="editForm.permissions.includes(perm.id)"
                                        @change="togglePermission(editForm, perm.id)"
                                        class="w-3.5 h-3.5 rounded border-slate-600 text-cyan-500"
                                    />
                                    <span class="text-[11px] text-slate-400">{{ actionLabels[perm.name.split('.')[1]] || perm.name.split('.')[1] }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions display (readonly) -->
                <div v-else class="px-5 py-3">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in role.permissions"
                            :key="perm.id"
                            class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded bg-slate-800 text-slate-400 border border-slate-700/40"
                        >
                            {{ perm.name }}
                        </span>
                        <span v-if="role.name === 'super-admin' && !role.permissions?.length" class="text-[11px] text-amber-400/80 italic">
                            ⚡ Toàn quyền (bypass tất cả)
                        </span>
                        <span v-if="role.name === 'super-admin' && role.permissions?.length" class="text-[11px] text-amber-400/80 italic ml-1">
                            ⚡ + bypass tất cả
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slideDown {
    animation: slideDown 0.3s ease-out;
}
</style>
