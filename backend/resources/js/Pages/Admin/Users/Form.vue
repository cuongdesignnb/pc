<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    user: { type: Object, default: null },
    roles: Array,
    permissions: Array,
    userRoles: { type: Array, default: () => [] },
});

const isEditing = !!props.user;

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    phone: props.user?.phone || '',
    role: props.user?.role || 'staff',
    password: '',
    password_confirmation: '',
    roles: props.userRoles?.map(id => id) || [],
});

function submit() {
    if (isEditing) {
        form.put(`/admin/users/${props.user.id}`);
    } else {
        form.post('/admin/users');
    }
}

function toggleRole(roleId) {
    const idx = form.roles.indexOf(roleId);
    if (idx >= 0) {
        form.roles.splice(idx, 1);
    } else {
        form.roles.push(roleId);
    }
}
</script>

<template>
    <AdminLayout :title="isEditing ? 'Sửa nhân viên' : 'Thêm nhân viên'">
        <div class="max-w-2xl">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <Link href="/admin/users" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <h1 class="text-xl font-bold text-white">{{ isEditing ? 'Sửa nhân viên' : 'Thêm nhân viên mới' }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5">{{ isEditing ? `Chỉnh sửa: ${user.name}` : 'Tạo tài khoản admin/nhân viên' }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info Card -->
                <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-6">
                    <h3 class="text-sm font-semibold text-slate-200 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Thông tin cơ bản
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Họ tên *</label>
                            <input v-model="form.name" type="text" class="w-full px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="Nguyễn Văn A" required />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Email *</label>
                            <input v-model="form.email" type="email" class="w-full px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="email@pcshop.vn" required />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Số điện thoại</label>
                            <input v-model="form.phone" type="text" class="w-full px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="0901234567" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-400">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Loại tài khoản *</label>
                            <select v-model="form.role" class="w-full px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-cyan-500/50">
                                <option value="admin">Admin</option>
                                <option value="staff">Nhân viên</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Password Card -->
                <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-6">
                    <h3 class="text-sm font-semibold text-slate-200 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Mật khẩu {{ isEditing ? '(để trống nếu không đổi)' : '' }}
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Mật khẩu {{ isEditing ? '' : '*' }}</label>
                            <input v-model="form.password" type="password" class="w-full px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="••••••••" :required="!isEditing" />
                            <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Xác nhận mật khẩu</label>
                            <input v-model="form.password_confirmation" type="password" class="w-full px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500/50" placeholder="••••••••" />
                        </div>
                    </div>
                </div>

                <!-- Roles Card -->
                <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-6">
                    <h3 class="text-sm font-semibold text-slate-200 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Vai trò phân quyền
                    </h3>
                    <p class="text-xs text-slate-500 mb-3">Chọn vai trò sẽ quyết định quyền truy cập của nhân viên</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all',
                                form.roles.includes(role.id)
                                    ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400'
                                    : 'bg-slate-800/30 border-slate-700/30 text-slate-400 hover:border-slate-600'
                            ]"
                        >
                            <input
                                type="checkbox"
                                :checked="form.roles.includes(role.id)"
                                @change="toggleRole(role.id)"
                                class="w-4 h-4 rounded border-slate-600 text-cyan-500 focus:ring-cyan-500/50"
                            />
                            <div>
                                <p class="text-sm font-medium">{{ role.name }}</p>
                                <p class="text-[10px] text-slate-500">{{ role.permissions?.length || 0 }} quyền</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-sm font-semibold rounded-lg shadow-lg shadow-cyan-500/20 hover:shadow-cyan-500/40 transition-all disabled:opacity-50"
                    >
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ isEditing ? 'Cập nhật' : 'Tạo tài khoản' }}
                    </button>
                    <Link href="/admin/users" class="px-4 py-2.5 text-sm text-slate-400 hover:text-white transition-colors">
                        Hủy
                    </Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
