@extends('layouts.admin')

@section('title', 'Passenger Discount & Pricing Rules | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Passenger Pricing Rules & Discounts</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure dynamic discounts (Student, Senior, PWD, Child, Infant) and foreign surcharges for traveler bookings.</p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Pricing Rules Table --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">tune</span>
                    <span>Configured Category Rules</span>
                </h3>
                <span class="text-xs text-slate-500 font-medium">{{ $rules->count() }} Categories Configured</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3.5 px-6">Category System Name</th>
                            <th class="py-3.5 px-6">Display Label</th>
                            <th class="py-3.5 px-6">Adjustment Type</th>
                            <th class="py-3.5 px-6">Amount (₱)</th>
                            <th class="py-3.5 px-6">Status</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rules as $rule)
                            <tr class="hover:bg-slate-50/80 transition">
                                {{-- System Name --}}
                                <td class="py-4 px-6 font-bold text-slate-900">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 font-mono text-xs">
                                        {{ $rule->category_name }}
                                    </span>
                                </td>

                                <form action="{{ route('admin.passenger-rules.update', $rule->id) }}" method="POST">
                                    @csrf
                                    
                                    {{-- Display Label --}}
                                    <td class="py-4 px-6">
                                        <input type="text" 
                                               name="display_label" 
                                               value="{{ $rule->display_label }}" 
                                               required 
                                               class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                    </td>

                                    {{-- Adjustment Type --}}
                                    <td class="py-4 px-6">
                                        <select name="adjustment_type" 
                                                class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            <option value="none" {{ $rule->adjustment_type === 'none' ? 'selected' : '' }}>None (Standard Rate)</option>
                                            <option value="discount" {{ $rule->adjustment_type === 'discount' ? 'selected' : '' }}>Discount (-)</option>
                                            <option value="surcharge" {{ $rule->adjustment_type === 'surcharge' ? 'selected' : '' }}>Surcharge (+)</option>
                                        </select>
                                    </td>

                                    {{-- Amount --}}
                                    <td class="py-4 px-6">
                                        <div class="relative w-32">
                                            <span class="absolute left-3 top-2 text-slate-400 font-bold">₱</span>
                                            <input type="number" 
                                                   name="amount" 
                                                   value="{{ (float)$rule->amount }}" 
                                                   step="0.01" 
                                                   min="0" 
                                                   required 
                                                   class="w-full pl-7 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        </div>
                                    </td>

                                    {{-- Active Status --}}
                                    <td class="py-4 px-6">
                                        <select name="is_active" 
                                                class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20"
                                                :class="'{{ $rule->is_active ? 'text-emerald-700' : 'text-slate-400' }}'">
                                            <option value="1" {{ $rule->is_active ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ !$rule->is_active ? 'selected' : '' }}>Disabled</option>
                                        </select>
                                    </td>

                                    {{-- Save Action --}}
                                    <td class="py-4 px-6 text-right">
                                        <button type="submit" 
                                                class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-sm transition flex items-center justify-center gap-1 ml-auto cursor-pointer">
                                            <span class="material-symbols-outlined text-[16px]">save</span>
                                            <span>Save</span>
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
