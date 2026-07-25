@extends('layouts.app')
@section('title', 'User Management')
@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    <x-page-header
        title="Users"
        subtitle="Manage system users and their access."
        buttonText="Create New User"
        buttonAction="openModal('create')"
    />

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">User</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Email</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Role</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Staff</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Status</th>
                        <th class="p-4 font-semibold tracking-wide hidden md:table-cell">Last Login</th>
                        <th class="p-4 pr-5 font-semibold tracking-wide text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5">
                            <div class="flex items-center gap-3">
                                <img src="{{ $user->photo_url }}" class="h-8 w-8 rounded-full flex-shrink-0">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="p-4 hidden md:table-cell text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                        <td class="p-4 hidden md:table-cell">
                            @php
                                $roleLabels = ['executive_director' => 'Executive Director', 'finance_manager' => 'Finance Manager', 'operations_hr_manager' => 'Operations & HR Manager', 'staff' => 'Staff'];
                                $roleColors = [
                                    'operations_hr_manager' => 'bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300',
                                    'executive_director' => 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300',
                                    'finance_manager' => 'bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300',
                                ];
                            @endphp
                            <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $roleColors[$user->role] ?? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' }}">
                                {{ $roleLabels[$user->role] ?? ucfirst($user->role) }}
                            </span>
                        </td>
                        <td class="p-4 hidden md:table-cell text-gray-500 dark:text-gray-400">{{ $user->staff->full_name ?? 'N/A' }}</td>
                        <td class="p-4 hidden md:table-cell">
                            @if($user->is_locked)
                                <span class="text-red-600 dark:text-red-400 text-xs font-bold">LOCKED</span>
                            @elseif(!$user->is_active)
                                <span class="text-amber-600 dark:text-amber-400 text-xs font-bold">INACTIVE</span>
                            @else
                                <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold">ACTIVE</span>
                            @endif
                        </td>
                        <td class="p-4 hidden md:table-cell text-xs text-gray-400 dark:text-gray-500">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</td>
                        <td class="p-4 pr-5">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="editUser({{ $user->id }})"
                                    class="w-7 h-7 bg-brand text-white rounded flex items-center justify-center hover:bg-brand-dark transition shadow-sm" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                                </button>
                                <button onclick="resetPassword({{ $user->id }})"
                                    class="w-7 h-7 bg-amber-500 text-white rounded flex items-center justify-center hover:bg-amber-600 transition shadow-sm" title="Reset Password">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                                </button>
                                <button onclick="openLockModal('{{ route('users.lock', $user->id) }}', '{{ $user->name }}', {{ $user->is_locked ? 'true' : 'false' }})"
                                    class="w-7 h-7 bg-gray-500 text-white rounded flex items-center justify-center hover:bg-gray-600 transition shadow-sm" title="{{ $user->is_locked ? 'Unlock' : 'Lock' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                                </button>
                                @if($user->id !== Auth::id())
                                <button onclick="openDeleteModal('{{ route('users.destroy', $user->id) }}', '{{ $user->name }}')"
                                    class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No users found yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="userModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">Add New User</h3>
            <button onclick="closeModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="userForm" method="POST" class="flex-1 overflow-y-auto">
            @csrf
            <div id="methodField"></div>
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="usr_name" placeholder="e.g. John Doe" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="usr_email" placeholder="john@example.com" required
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div id="passwordField" class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="usr_password" placeholder="Min. 8 characters"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Role</label>
                    <select name="role" id="usr_role"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                        <option value="staff">Staff</option>
                        <option value="operations_hr_manager">Operations & HR Manager</option>
                        <option value="executive_director">Executive Director</option>
                        <option value="finance_manager">Finance Manager</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Link to Staff</label>
                    <select name="staff_id" id="usr_staff_id"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition dark:text-gray-200">
                        <option value="">None</option>
                        @foreach($staffList as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="resetModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">Reset Password</h3>
            <button onclick="document.getElementById('resetModal').classList.add('hidden')" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="resetForm" method="POST" class="flex-1 overflow-y-auto">
            @csrf
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">New Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required placeholder="Min. 8 characters"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Confirm Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required placeholder="Repeat password"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition placeholder-gray-300 dark:placeholder-gray-500 dark:text-gray-200">
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="document.getElementById('resetModal').classList.add('hidden')"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button type="submit"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">Reset Password</button>
            </div>
        </form>
    </div>
</div>

{{-- Lock/Unlock Confirmation Modal --}}
<div id="lockModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[150] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden p-6 animate__fade-in">
        <div class="text-center">
            <div class="w-14 h-14 mx-auto bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4" id="lockModalTitle">Lock User</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Are you sure you want to <strong id="lockAction">lock</strong> user <strong id="lockUserName">this user</strong>?</p>
            <form id="lockForm" method="POST" class="mt-6">
                @csrf
                <div class="flex items-center justify-center gap-3">
                    <button type="button" onclick="closeLockModal()"
                        class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                    <button type="submit" id="lockSubmitBtn"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">Yes, Lock</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="deleteModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[150] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden p-6 animate__fade-in">
        <div class="text-center">
            <div class="w-14 h-14 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Delete Confirmation</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Are you sure you want to delete <strong id="deleteItemName">this item</strong>? This action cannot be undone.</p>
            <form id="deleteForm" method="POST" class="mt-6">
                @csrf @method('DELETE')
                <div class="flex items-center justify-center gap-3">
                    <button type="button" onclick="closeDeleteModal()"
                        class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                    <button type="submit"
                        class="bg-red-500 hover:bg-red-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.animate__slide-in-right { animation: slideInRight 0.2s ease-out; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.animate__fade-in { animation: fadeIn 0.15s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<script>
    function openModal() {
        document.getElementById('userModal').classList.remove('hidden');
        document.getElementById('modalTitle').innerText = 'Add New User';
        document.getElementById('userForm').action = '{{ route("users.store") }}';
        document.getElementById('methodField').innerHTML = '';
        document.getElementById('passwordField').classList.remove('hidden');
        document.getElementById('userForm').reset();
    }

    function editUser(id) {
        fetch('/users/' + id + '/edit')
            .then(r => r.json())
            .then(data => {
                document.getElementById('userModal').classList.remove('hidden');
                document.getElementById('modalTitle').innerText = 'Edit User: ' + data.name;
                document.getElementById('userForm').action = '/users/' + id;
                document.getElementById('methodField').innerHTML = '@method("PUT")';
                document.getElementById('passwordField').classList.add('hidden');
                document.getElementById('usr_name').value = data.name || '';
                document.getElementById('usr_email').value = data.email || '';
                document.getElementById('usr_role').value = data.role || 'staff';
                document.getElementById('usr_staff_id').value = data.staff_id || '';
            });
    }

    function resetPassword(id) {
        document.getElementById('resetModal').classList.remove('hidden');
        document.getElementById('resetForm').action = '/users/' + id + '/reset-password';
    }

    function closeModal() {
        document.getElementById('userModal').classList.add('hidden');
    }

    function openLockModal(action, name, isLocked) {
        document.getElementById('lockForm').action = action;
        document.getElementById('lockUserName').textContent = name;
        if (isLocked) {
            document.getElementById('lockModalTitle').textContent = 'Unlock User';
            document.getElementById('lockAction').textContent = 'unlock';
            document.getElementById('lockSubmitBtn').textContent = 'Yes, Unlock';
            document.getElementById('lockSubmitBtn').className = 'bg-emerald-500 hover:bg-emerald-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition';
        } else {
            document.getElementById('lockModalTitle').textContent = 'Lock User';
            document.getElementById('lockAction').textContent = 'lock';
            document.getElementById('lockSubmitBtn').textContent = 'Yes, Lock';
            document.getElementById('lockSubmitBtn').className = 'bg-gray-500 hover:bg-gray-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition';
        }
        document.getElementById('lockModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeLockModal() {
        document.getElementById('lockModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openDeleteModal(action, name) {
        document.getElementById('deleteForm').action = action;
        document.getElementById('deleteItemName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
</script>
@endsection
