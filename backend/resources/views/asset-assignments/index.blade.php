@extends('layouts.app')
@section('title', 'Asset Assignments')
@section('content')
<div class="space-y-6" x-data="{ search: '' }">
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    @if(Auth::user()->isOperationsHrManager())
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800 tracking-tight">Asset Assignments</h1>
            <p class="text-gray-500 text-sm mt-0.5">Assign assets to staff or programs.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openModal()"
                class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm flex items-center gap-2 transition">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                New Assignment
            </button>
        </div>
    </div>
    @else
    <x-page-header title="My Assigned Assets" subtitle="Assets assigned to you." />
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-semibold bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 font-semibold tracking-wide">Asset</th>
                        <th class="p-4 font-semibold tracking-wide">Assigned To</th>
                        <th class="p-4 font-semibold tracking-wide">Location</th>
                        <th class="p-4 font-semibold tracking-wide">Date</th>
                        <th class="p-4 font-semibold tracking-wide">Due Date</th>
                        <th class="p-4 font-semibold tracking-wide">Status</th>
                        @if(Auth::user()->isOperationsHrManager())
                        <th class="p-4 pr-5 font-semibold tracking-wide text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    @forelse($assignments as $assignment)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $assignment->asset->name ?? 'N/A' }}</div>
                            <div class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ $assignment->asset->asset_code ?? '' }}</div>
                        </td>
                        <td class="p-4">{{ $assignment->recipient_name }}</td>
                        <td class="p-4">{{ $assignment->location->name ?? 'N/A' }}</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $assignment->assigned_date ? $assignment->assigned_date->format('d M Y') : 'N/A' }}</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">{{ $assignment->due_date ? $assignment->due_date->format('d M Y') : 'N/A' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $assignment->status_badge_class }}">
                                {{ strtoupper($assignment->status) }}
                            </span>
                        </td>
                        @if(Auth::user()->isOperationsHrManager())
                        <td class="p-4 pr-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if($assignment->status !== 'returned')
                                <button onclick="editAssignment({{ $assignment->id }})"
                                    class="w-7 h-7 bg-amber-500 text-white rounded flex items-center justify-center hover:bg-amber-600 transition shadow-sm" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                                </button>
                                <button onclick="openCancelModal('{{ route('asset-assignments.cancel', $assignment) }}', '{{ $assignment->asset->name ?? 'this assignment' }}')"
                                    class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Cancel">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                @endif
                                <button onclick="openDeleteModal('{{ route('asset-assignments.destroy', $assignment) }}', '{{ $assignment->asset->name ?? 'this assignment' }}')"
                                    class="w-7 h-7 bg-red-500 text-white rounded flex items-center justify-center hover:bg-red-600 transition shadow-sm" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </button>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                <p class="text-gray-400 dark:text-gray-500 text-sm font-medium">No assignments found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if(Auth::user()->isOperationsHrManager())
{{-- Assignment Modal -- slide-in from right --}}
<div id="assignmentModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[100] flex items-center justify-end p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden animate__slide-in-right">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-900 dark:text-white tracking-wide">New Assignment</h3>
            <button onclick="closeModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition p-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="assignmentForm" action="{{ route('asset-assignments.store') }}" method="POST" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" id="formMethod" name="_method" value="POST">
            <div class="p-6 space-y-5 bg-gray-50/30 dark:bg-gray-900/30">
                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Asset <span class="text-red-500">*</span></label>
                        <select name="asset_id" id="field_asset_id" required
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                            <option value="">Select Asset</option>
                            @foreach($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Assign To Type <span class="text-red-500">*</span></label>
                        <select name="assigned_to_type" id="field_assigned_to_type" required onchange="toggleAssigneeType()"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                            <option value="staff">Staff</option>
                            <option value="program">Program</option>
                        </select>
                    </div>
                </div>
                <div id="staffField" class="space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Staff <span class="text-red-500">*</span></label>
                    <select name="assigned_to_id" id="field_assigned_to_id"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                        <option value="">Select Staff</option>
                        @foreach($staffList as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="programField" class="hidden space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Program <span class="text-red-500">*</span></label>
                    <select name="assigned_to_id_program"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                        <option value="">Select Program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Location <span class="text-red-500">*</span></label>
                        <select name="location_id" id="field_location_id" required
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                            <option value="">Select Location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" id="field_quantity" required min="1" value="1"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 dark:placeholder-gray-500 focus:outline-none focus:border-brand transition">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Assigned Date <span class="text-red-500">*</span></label>
                        <input type="date" name="assigned_date" id="field_assigned_date" required value="{{ date('Y-m-d') }}"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Due Date</label>
                        <input type="date" name="due_date" id="field_due_date"
                            class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                    </div>
                </div>
                <div id="editStatusField" class="hidden space-y-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 tracking-wide">Status</label>
                    <select name="status" id="field_status"
                        class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-3.5 py-2.5 text-sm dark:text-gray-200 focus:outline-none focus:border-brand transition">
                        <option value="assigned">Assigned</option>
                        <option value="active">Active</option>
                        <option value="returned">Returned</option>
                    </select>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-center gap-3 bg-white dark:bg-gray-800">
                <button type="button" onclick="closeModal()"
                    class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-10 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                    class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-12 py-2.5 rounded-xl shadow-sm transition">
                    Assign
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Cancel Confirmation Modal --}}
<div id="cancelModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden z-[150] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden p-6 animate__fade-in">
        <div class="text-center">
            <div class="w-14 h-14 mx-auto bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-4">Cancel Assignment</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Are you sure you want to cancel assignment of <strong id="cancelItemName">this item</strong>?</p>
            <form id="cancelForm" method="POST" class="mt-6">
                @csrf
                <div class="flex items-center justify-center gap-3">
                    <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden'); document.body.style.overflow = 'auto';"
                        class="border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition">Close</button>
                    <button type="submit"
                        class="bg-amber-500 hover:bg-amber-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl shadow-sm transition">Yes, Cancel</button>
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
    function toggleAssigneeType() {
        const type = document.getElementById('field_assigned_to_type').value;
        document.getElementById('staffField').classList.toggle('hidden', type !== 'staff');
        document.getElementById('programField').classList.toggle('hidden', type !== 'program');
    }

    function openModal() {
        document.getElementById('assignmentModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        document.getElementById('modalTitle').innerText = 'New Assignment';
        document.getElementById('assignmentForm').action = '{{ route("asset-assignments.store") }}';
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('submitBtn').innerText = 'Assign';
        document.getElementById('assignmentForm').reset();
        document.getElementById('editStatusField').classList.add('hidden');
        toggleAssigneeType();
    }

    function editAssignment(id) {
        fetch('/asset-assignments/' + id + '/edit')
            .then(r => r.json())
            .then(data => {
                document.getElementById('assignmentModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                document.getElementById('modalTitle').innerText = 'Edit Assignment';
                document.getElementById('assignmentForm').action = '/asset-assignments/' + id;
                document.getElementById('formMethod').value = 'PUT';
                document.getElementById('submitBtn').innerText = 'Update';
                document.getElementById('field_location_id').value = data.location_id || '';
                document.getElementById('field_due_date').value = data.due_date ? data.due_date.substring(0, 10) : '';
                document.getElementById('field_status').value = data.status || 'assigned';
                document.getElementById('editStatusField').classList.remove('hidden');
            });
    }

    function closeModal() {
        document.getElementById('assignmentModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openCancelModal(action, name) {
        document.getElementById('cancelForm').action = action;
        document.getElementById('cancelItemName').textContent = name;
        document.getElementById('cancelModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
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
@endif
@endsection