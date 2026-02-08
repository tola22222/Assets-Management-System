@extends('layouts.app')

@section('content')
    <div class="space-y-6">

        <x-page-header
            title="Asset Inventory Stock"
            subtitle="Track which assets are currently deployed."
            buttonText="New Assignment"
            buttonAction="document.getElementById('assignModal').classList.remove('hidden')"
        />

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Asset</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-center">Qty</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Assigned To</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Location</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Date</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($assignments as $assign)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700">{{ $assign->asset->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $assign->asset->serial_number }}</div>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded-md text-sm font-bold border border-slate-200">
                                    {{ $assign->quantity }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg flex items-center justify-center {{ $assign->assigned_to_type === 'staff' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                                        @if($assign->assigned_to_type === 'staff')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"></path></svg>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-700">{{ $assign->assignee_name }}</div>
                                        <div class="text-[10px] uppercase font-semibold tracking-wider text-slate-400">
                                            {{ $assign->assigned_to_type }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">{{ $assign->location->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $assign->assigned_date }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase {{ $assign->status == 'assigned' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $assign->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if ($assign->status == 'assigned')
                                    <form action="{{ route('asset-assignments.update', $assign) }}" method="POST">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="returned">
                                        <input type="hidden" name="location_id" value="{{ $assign->location_id }}">
                                        <button class="text-indigo-600 text-xs font-bold hover:underline">
                                            Mark Returned
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div id="assignModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h2 class="text-xl font-bold mb-4">Assign Asset</h2>
            <form action="{{ route('asset-assignments.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Select Asset</label>
                        <select name="asset_id" class="w-full mt-1 p-2 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->serial_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Quantity</label>
                        <input type="number" name="quantity" min="1" value="1" class="w-full mt-1 p-2 border border-slate-200 rounded-lg">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Assign To</label>
                        <select name="assigned_to_type" id="typeSelect" class="w-full mt-1 p-2 border border-slate-200 rounded-lg">
                            <option value="staff">Staff Member</option>
                            <option value="program">Program</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Recipient</label>
                        <select name="assigned_to_id" id="recipientSelect" class="w-full mt-1 p-2 border border-slate-200 rounded-lg">
                            @foreach ($staffs as $s)
                                <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Location</label>
                        <select name="location_id" class="w-full mt-1 p-2 border border-slate-200 rounded-lg">
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Date</label>
                        <input type="date" name="assigned_date" value="{{ date('Y-m-d') }}" class="w-full mt-1 p-2 border border-slate-200 rounded-lg">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')" class="text-slate-400 px-4 font-bold">Cancel</button>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">Complete Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Use PHP to pass staff and program data to JavaScript
        const staffs = @json($staffs);
        const programs = @json($programs ?? []); // Ensure $programs is passed from Controller

        document.getElementById('typeSelect').addEventListener('change', function() {
            const recipientSelect = document.getElementById('recipientSelect');
            recipientSelect.innerHTML = ''; // Clear current options

            if (this.value === 'staff') {
                staffs.forEach(s => {
                    let option = new Option(s.full_name, s.id);
                    recipientSelect.add(option);
                });
            } else {
                programs.forEach(p => {
                    let option = new Option(p.name, p.id);
                    recipientSelect.add(option);
                });
            }
        });
    </script>
@endsection
