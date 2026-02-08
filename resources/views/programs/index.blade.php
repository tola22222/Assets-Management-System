@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-page-header
            title="Program Management"
            subtitle="Manage ICT, English, Dream, and Scholarship programs."
            buttonText="Create New Program"
            buttonAction="document.getElementById('addProgramModal').classList.remove('hidden')"
        />

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Program Name</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Description</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Created Date</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($programs as $program)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-bold text-slate-700">{{ $program->name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500 max-w-xs truncate">
                                {{ $program->description ?? 'No description provided' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $program->created_at?->format('M d, Y') ?? 'No Date' }}
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-3">
                                <button onclick="openEditModal({{ $program }})"
                                    class="text-indigo-600 font-bold hover:text-indigo-900 transition text-sm">Edit</button>
                                <form action="{{ route('programs.destroy', $program) }}" method="POST"
                                    onsubmit="return confirm('Are you sure?')">
                                    @csrf @method('DELETE')
                                    <button
                                        class="text-red-500 font-bold hover:text-red-700 transition text-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div id="addProgramModal"
        class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h2 class="text-xl font-bold mb-4">New Program</h2>
            <form action="{{ route('programs.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Program Name</label>
                    <input type="text" name="name" placeholder="e.g., ICT, English, etc."
                        class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                        required>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full mt-1 p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('addProgramModal').classList.add('hidden')"
                        class="text-slate-400 px-4">Cancel</button>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">Save
                        Program</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editProgramModal"
        class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h2 class="text-xl font-bold mb-4">Edit Program</h2>
            <form id="editForm" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Program Name</label>
                    <input type="text" name="name" id="edit_name"
                        class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"
                        required>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('editProgramModal').classList.add('hidden')"
                        class="text-slate-400 px-4 font-bold">Cancel</button>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">Update
                        Program</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(program) {
            const form = document.getElementById('editForm');
            form.action = '/programs/' + program.id;
            document.getElementById('edit_name').value = program.name;
            document.getElementById('edit_description').value = program.description || '';
            document.getElementById('editProgramModal').classList.remove('hidden');
        }
    </script>
@endsection
