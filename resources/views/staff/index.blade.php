@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-slate-800">Staff Members</h1>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-indigo-700 transition">
            + Add Staff
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Name</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Position</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Status</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($staffs as $staff)
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-slate-200 overflow-hidden border border-slate-100">
                            @if($staff->photo_path)
                                <img src="{{ asset('storage/'.$staff->photo_path) }}" class="h-full w-full object-cover">
                            @else
                                <div class="h-full w-full flex items-center justify-center text-slate-400 text-xs">No Image</div>
                            @endif
                        </div>
                        <div class="font-bold text-slate-700">{{ $staff->full_name }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $staff->position ?? 'N/A' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-lg text-xs font-bold {{ $staff->status == 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ ucfirst($staff->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right flex justify-end gap-3">
                        <button onclick="openEditModal({{ $staff }})" class="text-indigo-600 font-bold hover:text-indigo-900 transition">Edit</button>
                        <form action="{{ route('staff.destroy', $staff) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 font-bold hover:text-red-700 transition">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4 text-slate-800">New Staff Member</h2>
        <form action="{{ route('staff.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Full Name</label>
                <input type="text" name="full_name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none" required>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Email</label>
                    <input type="email" name="email" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Phone</label>
                    <input type="text" name="phone" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Position</label>
                    <input type="text" name="position" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Hire Date</label>
                    <input type="date" name="hire_date" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Staff Photo</label>
                <input type="file" name="photo" class="w-full mt-1 text-sm text-slate-500">
            </div>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-400 px-4">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h2 class="text-xl font-bold mb-4 text-slate-800">Edit Staff Member</h2>
        <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf @method('PUT')
            
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Full Name</label>
                <input type="text" name="full_name" id="edit_full_name" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none" required>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Email</label>
                    <input type="email" name="email" id="edit_email" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Position</label>
                    <input type="text" name="position" id="edit_position" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Hire Date</label>
                    <input type="date" name="hire_date" id="edit_hire_date" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Status</label>
                <select name="status" id="edit_status" class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Update Photo</label>
                <input type="file" name="photo" class="w-full mt-1 text-sm text-slate-500">
            </div>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 px-4">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">Update Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(staff) {
        const form = document.getElementById('editForm');
        form.action = '/staff/' + staff.id;
        
        // Populate all fields correctly
        document.getElementById('edit_full_name').value = staff.full_name;
        document.getElementById('edit_email').value = staff.email || '';
        document.getElementById('edit_phone').value = staff.phone || '';
        document.getElementById('edit_position').value = staff.position || '';
        document.getElementById('edit_hire_date').value = staff.hire_date || '';
        document.getElementById('edit_status').value = staff.status;
        
        document.getElementById('editModal').classList.remove('hidden');
    }
</script>
@endsection