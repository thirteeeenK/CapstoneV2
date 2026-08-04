@extends('layouts.admin')

@section('title', 'Add New Add-on/Transfer | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Add New Add-on or Transfer</h1>
                <p class="text-xs text-slate-500 mt-1">Create an add-on, set dynamic pricing tiers, and define surcharges.
                </p>
            </div>
            <a href="{{ route('admin.addons.index') }}"
                class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Add-ons
            </a>
        </div>

        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-xs px-4 py-3 rounded-md mb-6 space-y-1">
                <p class="font-bold">Please correct the errors below:</p>
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.addons.store') }}" method="POST" class="grid grid-cols-12 gap-8 items-start"
            x-data="addonForm()">
            @csrf

            <!-- Main Form Column (Left) -->
            <div class="col-span-12 lg:col-span-8 space-y-6">
                <!-- General Info -->
                <section class="bg-white rounded-lg p-6 border border-slate-200 shadow-sm">
                    <h2
                        class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-ocean-600 rounded-full"></span>
                        General Information
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Destination
                                <span class="text-rose-500">*</span></label>
                            <select name="destination_id" required
                                class="w-full bg-slate-50 border border-slate-300 rounded-md py-2 px-3 text-sm focus:ring-ocean-500">
                                <option value="">Select Destination..</option>
                                @foreach($destinations as $dest)
                                    <option value="{{ $dest->id }}" {{ old('destination_id') == $dest->id ? 'selected' : '' }}>
                                        {{ $dest->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Name <span
                                    class="text-rose-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                placeholder="e.g. Airport to Hotel Transfer"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md py-2 px-3 text-sm focus:ring-ocean-500" />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Type <span
                                    class="text-rose-500">*</span></label>
                            <input type="text" name="type" value="{{ old('type') }}" required
                                placeholder="e.g. Transfer, Equipment Rental"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md py-2 px-3 text-sm focus:ring-ocean-500" />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <label
                                class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Description</label>
                            <textarea name="description" rows="3"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md py-2 px-3 text-sm focus:ring-ocean-500">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </section>

                <!-- Inclusions -->
                <section class="bg-white rounded-lg p-6 border border-slate-200 shadow-sm">
                    <h2
                        class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-ocean-600 rounded-full"></span>
                        Inclusions
                    </h2>

                    <div class="space-y-3">
                        <template x-for="(item, index) in inclusions" :key="index">
                            <div class="flex gap-2 items-center">
                                <input type="text" x-model="inclusions[index]" :name="`inclusions[]`"
                                    placeholder="e.g. All-in service"
                                    class="flex-1 bg-slate-50 border border-slate-300 rounded-md py-2 px-3 text-sm focus:ring-ocean-500" />
                                <button type="button" @click="inclusions.splice(index, 1)"
                                    class="p-2 text-rose-500 hover:bg-rose-50 rounded-md transition-colors"><span
                                        class="material-symbols-outlined text-[20px]">delete</span></button>
                            </div>
                        </template>
                        <button type="button" @click="inclusions.push('')"
                            class="inline-flex items-center gap-1 text-sm font-medium text-ocean-600 hover:text-ocean-700">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span> Add Inclusion
                        </button>
                    </div>
                </section>

                <!-- Pricing Tiers -->
                <section class="bg-white rounded-lg p-6 border border-slate-200 shadow-sm">
                    <h2
                        class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-ocean-600 rounded-full"></span>
                        Pricing Tiers
                    </h2>

                    <div class="space-y-4">
                        <template x-for="(tier, index) in pricingTiers" :key="index">
                            <div
                                class="grid grid-cols-12 gap-3 items-center bg-slate-50 p-3 rounded-lg border border-slate-200">
                                <div class="col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Min Pax</label>
                                    <input type="number" x-model="tier.min_pax" :name="`pricing_tiers[${index}][min_pax]`"
                                        placeholder="1"
                                        class="w-full bg-white border border-slate-300 rounded-md py-1.5 px-3 text-sm" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Max Pax</label>
                                    <input type="number" x-model="tier.max_pax" :name="`pricing_tiers[${index}][max_pax]`"
                                        placeholder="1"
                                        class="w-full bg-white border border-slate-300 rounded-md py-1.5 px-3 text-sm" />
                                </div>
                                <div class="col-span-5">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Rate (₱)</label>
                                    <input type="number" step="any" x-model="tier.rate"
                                        :name="`pricing_tiers[${index}][rate]`" placeholder="1850"
                                        class="w-full bg-white border border-slate-300 rounded-md py-1.5 px-3 text-sm" />
                                </div>
                                <div class="col-span-1 flex justify-end mt-4">
                                    <button type="button" @click="pricingTiers.splice(index, 1)"
                                        class="p-1.5 text-rose-500 hover:bg-rose-100 rounded transition-colors"><span
                                            class="material-symbols-outlined text-[18px]">close</span></button>
                                </div>
                            </div>
                        </template>
                        <button type="button" @click="pricingTiers.push({min_pax: '', max_pax: '', rate: ''})"
                            class="inline-flex items-center gap-1 text-sm font-medium text-ocean-600 hover:text-ocean-700">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span> Add Pricing Tier
                        </button>
                    </div>
                </section>

                <!-- Surcharges -->
                <section class="bg-white rounded-lg p-6 border border-slate-200 shadow-sm">
                    <h2
                        class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-ocean-600 rounded-full"></span>
                        Surcharges / Extra Fees
                    </h2>

                    <div class="space-y-4">
                        <template x-for="(surcharge, index) in surcharges" :key="index">
                            <div
                                class="grid grid-cols-12 gap-3 items-center bg-slate-50 p-3 rounded-lg border border-slate-200">
                                <div class="col-span-5">
                                    <label
                                        class="block text-[10px] font-bold text-slate-500 uppercase">Name/Condition</label>
                                    <input type="text" x-model="surcharge.name" :name="`surcharges[${index}][name]`"
                                        placeholder="e.g. Station 1 Drop-off"
                                        class="w-full bg-white border border-slate-300 rounded-md py-1.5 px-3 text-sm" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Amount (₱)</label>
                                    <input type="number" step="any" x-model="surcharge.amount"
                                        :name="`surcharges[${index}][amount]`" placeholder="200"
                                        class="w-full bg-white border border-slate-300 rounded-md py-1.5 px-3 text-sm" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Type</label>
                                    <select x-model="surcharge.type" :name="`surcharges[${index}][type]`"
                                        class="w-full bg-white border border-slate-300 rounded-md py-1.5 px-3 text-sm">
                                        <option value="per_pax">Per Pax</option>
                                        <option value="fixed">Fixed</option>
                                    </select>
                                </div>
                                <div class="col-span-1 flex justify-end mt-4">
                                    <button type="button" @click="surcharges.splice(index, 1)"
                                        class="p-1.5 text-rose-500 hover:bg-rose-100 rounded transition-colors"><span
                                            class="material-symbols-outlined text-[18px]">close</span></button>
                                </div>
                            </div>
                        </template>
                        <button type="button" @click="surcharges.push({name: '', amount: '', type: 'per_pax'})"
                            class="inline-flex items-center gap-1 text-sm font-medium text-ocean-600 hover:text-ocean-700">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span> Add Surcharge
                        </button>
                    </div>
                </section>
            </div>

            <!-- Sticky Sidebar (Right) -->
            <div class="hidden lg:block lg:col-span-4">
                <div class="sticky top-6 bg-white rounded-lg p-6 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">Publishing</h3>
                    <p class="text-xs text-slate-500 mb-4 leading-relaxed">Review your changes and click the button below to
                        publish.</p>

                    <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1.5">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-xs font-bold text-slate-800">Show to Public Users</span>
                            <input type="checkbox" name="is_shown" value="1" checked
                                class="w-4 h-4 text-ocean-600 rounded border-slate-300 focus:ring-ocean-500">
                        </label>
                        <p class="text-[11px] text-slate-500 leading-tight">If unchecked, this remains hidden from public.
                        </p>
                    </div>

                    <button type="submit"
                        class="w-full h-10 px-4 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span> Save Add-on
                    </button>
                </div>
            </div>

            <!-- Mobile Sticky Button -->
            <div
                class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-t border-slate-200 p-4 lg:hidden">
                <button type="submit"
                    class="w-full h-11 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span> Save Add-on
                </button>
            </div>
        </form>
    </div>

    <!-- Alpine Data Component -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('addonForm', () => ({
                inclusions: {!! json_encode(old('inclusions', [''])) !!},
                pricingTiers: {!! json_encode(old('pricing_tiers', [['min_pax' => '', 'max_pax' => '', 'rate' => '']])) !!},
                surcharges: {!! json_encode(old('surcharges', [])) !!}
            }))
        })
    </script>
@endsection