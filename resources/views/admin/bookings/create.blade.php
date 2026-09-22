@extends('layouts.admin')

@section('title', 'Create Booking on Behalf of Customer | SunnyTrips Admin')

@section('content')
<div class="max-w-6xl mx-auto pb-16 font-body"
     x-data="{
          isWalkIn: false,
         selectedUser: {{ $selectedUser ? json_encode(['id' => $selectedUser->id, 'name' => $selectedUser->name, 'email' => $selectedUser->email, 'phone_number' => $selectedUser->phone_number, 'address' => $selectedUser->address]) : 'null' }},
         userSearch: '',
         userSearchResults: [],
         isSearchingUser: false,
         searchTimer: null,
         autoCreateAccount: false,
         bookingSource: 'admin_walk_in',
         contactName: '{{ $selectedUser ? addslashes($selectedUser->name) : '' }}',
         contactEmail: '{{ $selectedUser ? addslashes($selectedUser->email) : '' }}',
         contactPhone: '{{ $selectedUser ? addslashes($selectedUser->phone_number ?? '') : '' }}',
         specialRequests: '',
         adminNotes: '',
         initialStatus: 'approved',
         paymentMethod: 'Cash',
         paymentReference: '',
         adminDiscount: 0,
         adminSurcharge: 0,
         adjustmentReason: '',
         temporaryPassword: 'Sunny' + Math.floor(1000 + Math.random() * 9000) + '!',
          showPassword: false,
         generatePassword() {
             this.temporaryPassword = 'Sunny' + Math.floor(1000 + Math.random() * 9000) + '!';
         },

         // Catalog data
         destinations: @js($destinations),
         categoryRules: @js($categoryRules),

         // Modal state for adding items
         showAddModal: false,
         modalType: 'room',
         modalDestId: '',
         modalHotelId: '',
         modalRoomId: '',
         modalPkgId: '',
         modalActId: '',
         modalAddonId: '',
         modalCheckIn: '',
         modalCheckOut: '',
         modalQty: 1,
         modalPax: 2,
          checkingAvail: false,
          availResult: null,
          submitting: false,

         // Active Booking Items
         items: [],

         // Passenger Manifest
         passengers: [],

         init() {
             if (this.selectedUser) {
                 this.passengers.push({
                     full_name: this.selectedUser.name,
                     category: 'Adult'
                 });
             }
         },

         async searchUsers() {
             clearTimeout(this.searchTimer);
             if (!this.userSearch || this.userSearch.length < 2) {
                 this.userSearchResults = [];
                 return;
             }
             this.searchTimer = setTimeout(async () => {
                 this.isSearchingUser = true;
                 try {
                     const res = await fetch(`{{ route('admin.bookings.search-users') }}?query=${encodeURIComponent(this.userSearch)}`, {
                         headers: { 'X-Requested-With': 'XMLHttpRequest' }
                     });
                     this.userSearchResults = await res.json();
                 } catch (e) {
                     console.error(e);
                 } finally {
                     this.isSearchingUser = false;
                 }
             }, 300);
         },

         selectUser(u) {
             this.selectedUser = u;
             this.contactName = u.name;
             this.contactEmail = u.email;
             this.contactPhone = u.phone_number || '';
             this.userSearch = '';
             this.userSearchResults = [];
             if (this.passengers.length === 0) {
                 this.passengers.push({ full_name: u.name, category: 'Adult' });
             } else if (!this.passengers[0].full_name) {
                 this.passengers[0].full_name = u.name;
             }
         },

         clearSelectedUser() {
             this.selectedUser = null;
             this.contactName = '';
             this.contactEmail = '';
             this.contactPhone = '';
         },

         openAddModal(type) {
             this.modalType = type;
             this.modalDestId = this.destinations.length > 0 ? this.destinations[0].id : '';
             this.modalHotelId = '';
             this.modalRoomId = '';
             this.modalPkgId = '';
             this.modalActId = '';
             this.modalAddonId = '';
             this.modalCheckIn = new Date().toISOString().split('T')[0];
             const tomorrow = new Date();
             tomorrow.setDate(tomorrow.getDate() + 1);
             this.modalCheckOut = tomorrow.toISOString().split('T')[0];
             this.modalQty = 1;
             this.modalPax = type === 'room' ? 2 : 1;
             this.availResult = null;
             this.showAddModal = true;
         },

         get currentHotels() {
             const dest = this.destinations.find(d => d.id == this.modalDestId);
             return dest ? dest.hotels : [];
         },

         get currentRooms() {
             const hotel = this.currentHotels.find(h => h.id == this.modalHotelId);
             return hotel ? hotel.rooms : [];
         },

         get currentPackages() {
             const dest = this.destinations.find(d => d.id == this.modalDestId);
             return dest ? dest.packages : [];
         },

         get currentActivities() {
             const dest = this.destinations.find(d => d.id == this.modalDestId);
             return dest ? dest.activities : [];
         },

         get currentAddOns() {
             const dest = this.destinations.find(d => d.id == this.modalDestId);
             return dest ? dest.add_ons : [];
         },

         async checkRoomAvail() {
             if (!this.modalRoomId || !this.modalCheckIn || !this.modalCheckOut) return;
             this.checkingAvail = true;
             this.availResult = null;
             try {
                 const res = await fetch(`{{ route('admin.bookings.check-availability') }}`, {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}',
                         'X-Requested-With': 'XMLHttpRequest'
                     },
                     body: JSON.stringify({
                         room_id: this.modalRoomId,
                         check_in_date: this.modalCheckIn,
                         check_out_date: this.modalCheckOut
                     })
                 });
                 this.availResult = await res.json();
             } catch (e) {
                 console.error(e);
             } finally {
                 this.checkingAvail = false;
             }
         },

          get todayStr() {
              return new Date().toISOString().split('T')[0];
          },

          get selectedModalRoom() {
              return this.currentRooms.find(r => r.id == this.modalRoomId) || null;
          },

          get modalRoomExtraHeads() {
              const room = this.selectedModalRoom;
              if (!room) return { extra: 0, fee: 0, base: 2, max: 0 };
              const base = parseInt(room.base_occupancy) || 2;
              const pax = parseInt(this.modalPax) || 1;
              return {
                  extra: Math.max(0, pax - base),
                  fee: parseFloat(room.extra_person_fee) || 0,
                  base,
                  max: parseInt(room.max_occupancy) || 0,
              };
          },

          get itemNightsPreview() {
              if (this.modalType !== 'room' || !this.modalCheckIn || !this.modalCheckOut) return 1;
              return Math.max(1, Math.round((new Date(this.modalCheckOut) - new Date(this.modalCheckIn)) / 86400000));
          },

          get minCheckOut() {
              const base = this.modalCheckIn || this.todayStr;
              const d = new Date(base + 'T00:00:00');
              d.setDate(d.getDate() + 1);
              return d.toISOString().split('T')[0];
          },

          get selectedModalAddon() {
              return this.currentAddOns.find(a => a.id == this.modalAddonId) || null;
          },

          addonRateForPax(addon, pax) {
              pax = Math.max(1, parseInt(pax) || 1);
              const tiers = addon?.pricing_tiers || [];
              if (tiers.length > 0) {
                  let match = tiers.find(t => pax >= (parseInt(t.min_pax) || 1) && pax <= (parseInt(t.max_pax) || 999));
                  if (!match) match = tiers[tiers.length - 1];
                  if (match.rate !== undefined && match.rate !== null && match.rate !== '') return parseFloat(match.rate) || 0;
                  if (match.rate_per_pax !== undefined && match.rate_per_pax !== null && match.rate_per_pax !== '') return parseFloat(match.rate_per_pax) || 0;
                  if (match.total_rate) return (parseFloat(match.total_rate) || 0) / pax;
              }
              return 0;
          },

          isTierMatched(tier) {
              const effPax = Math.max(parseInt(this.modalPax) || 1, parseInt(this.modalQty) || 1);
              return effPax >= (parseInt(tier.min_pax) || 1) && effPax <= (parseInt(tier.max_pax) || 999);
          },

          isPerPersonRate(rateStr) {
              const s = String(rateStr || '').toLowerCase();
              // Mirror of ActivityModel::isPerPersonRate(): default per-person,
              // only explicit flat markers opt out into a one-charge group rate.
              return !/flat|per group|group rate|per unit|\/unit|per van|per boat|per trip|private|\/hour|per hour|hourly|per booking|per session/i.test(s);
          },

          parseActivityRate(rateStr, pax) {
              pax = Math.max(1, parseInt(pax) || 1);
              const str = String(rateStr || '');
              const range = str.match(/(\d[\d,.]*)\s*[-–—]\s*[^\d]*(\d[\d,.]*)/u);
              if (range) {
                  const min = parseFloat(range[1].replace(/,/g, '')) || 0;
                  const max = parseFloat(range[2].replace(/,/g, '')) || 0;
                  return pax <= 1 ? min : max;
              }
              return parseFloat(str.replace(/[^\d.]/g, '')) || 0;
          },

          addItemFromModal() {
             let itemObj = {
                 item_type: this.modalType,
                 quantity: parseInt(this.modalQty) || 1,
                 selected_pax: parseInt(this.modalPax) || 1,
                 check_in_date: null,
                 check_out_date: null,
                 nights: 1,
                 unit_price: 0,
                 subtotal: 0,
                 title: '',
                 subtitle: ''
             };

               if (this.modalType === 'room') {
                   const room = this.currentRooms.find(r => r.id == this.modalRoomId);
                   if (!room) return;
                   if (this.availResult && itemObj.quantity > this.availResult.remaining) {
                       alert(`Only ${this.availResult.remaining} unit(s) available for these dates — reduce Quantity to ${this.availResult.remaining}.`);
                       return;
                   }
                   const maxOcc = parseInt(room.max_occupancy) || 0;
                   if (maxOcc > 0) itemObj.selected_pax = Math.min(itemObj.selected_pax, maxOcc);
                 const checkIn = new Date(this.modalCheckIn);
                 const checkOut = new Date(this.modalCheckOut);
                 const nights = Math.max(1, Math.round((checkOut - checkIn) / (1000 * 60 * 60 * 24)));
                 
                 let basePrice = parseFloat(room.base_price) || 0;
                 let extraFee = parseFloat(room.extra_person_fee) || 0;
                 let basePax = parseInt(room.base_occupancy) || 2;
                 let pax = itemObj.selected_pax;
                 let rate = basePrice;
                 if (pax > basePax) {
                     rate += (pax - basePax) * extraFee;
                 }

                 itemObj.item_id = room.id;
                 itemObj.title = room.room_name;
                 const hObj = this.currentHotels.find(h => h.id == this.modalHotelId);
                 itemObj.subtitle = (hObj?.hotel_name || hObj?.name || 'Hotel') + ` (${nights} night${nights > 1 ? 's' : ''})`;
                 itemObj.check_in_date = this.modalCheckIn;
                 itemObj.check_out_date = this.modalCheckOut;
                 itemObj.nights = nights;
                 itemObj.unit_price = rate;
                 itemObj.subtotal = rate * itemObj.quantity * nights;
             } else if (this.modalType === 'package') {
                 const pkg = this.currentPackages.find(p => p.id == this.modalPkgId);
                 if (!pkg) return;
                  itemObj.item_id = pkg.id;
                  itemObj.title = pkg.name;
                  itemObj.subtitle = 'Tour Package';
                  itemObj.unit_price = parseFloat(pkg.price) || 0;
                  itemObj.subtotal = itemObj.unit_price * Math.max(itemObj.selected_pax, itemObj.quantity);
             } else if (this.modalType === 'activity') {
                  const act = this.currentActivities.find(a => a.id == this.modalActId);
                  if (!act) return;
                  const actEffPax = Math.max(itemObj.selected_pax, itemObj.quantity);
                  const actRate = this.parseActivityRate(act.rate, actEffPax);
                  itemObj.item_id = act.id;
                  itemObj.title = act.activity_name;
                  itemObj.subtitle = 'Activity • ' + (act.category || 'Tour');
                  itemObj.unit_price = actRate;
                  itemObj.subtotal = this.isPerPersonRate(act.rate) ? actRate * actEffPax : actRate * itemObj.quantity;
             } else if (this.modalType === 'addon') {
                  const addon = this.currentAddOns.find(a => a.id == this.modalAddonId);
                  if (!addon) return;
                  const addonEffPax = Math.max(itemObj.selected_pax, itemObj.quantity);
                  const rate = this.addonRateForPax(addon, addonEffPax);
                  itemObj.item_id = addon.id;
                  itemObj.title = addon.name;
                  itemObj.subtitle = 'Add-on Service';
                  itemObj.unit_price = rate;
                  itemObj.subtotal = rate * addonEffPax;
             }

             this.items.push(itemObj);
             this.showAddModal = false;
         },

         removeItem(idx) {
             this.items.splice(idx, 1);
         },

         addPassenger() {
             this.passengers.push({
                 full_name: '',
                 category: 'Adult'
             });
         },

         removePassenger(idx) {
             this.passengers.splice(idx, 1);
         },

         get grossTotal() {
             return this.items.reduce((acc, i) => acc + (parseFloat(i.subtotal) || 0), 0);
         },

         get passengerAdjustmentTotal() {
             let diff = 0;
             this.passengers.forEach(p => {
                 const rule = this.categoryRules.find(r => r.category_name === p.category);
                 if (rule) {
                     const amt = parseFloat(rule.amount) || 0;
                     if (rule.adjustment_type === 'discount') {
                         diff -= amt;
                     } else if (rule.adjustment_type === 'surcharge') {
                         diff += amt;
                     }
                 }
             });
             return diff;
         },

         get netTotal() {
             const gross = this.grossTotal;
             const paxAdj = this.passengerAdjustmentTotal;
             const disc = parseFloat(this.adminDiscount) || 0;
             const surch = parseFloat(this.adminSurcharge) || 0;
             return Math.max(0, gross + paxAdj - disc + surch);
         }
     }">

    {{-- Header & Back --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.bookings.index') }}" class="text-xs font-bold text-ocean-600 hover:text-ocean-700 hover:underline flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Back to Bookings</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Create Booking on Behalf of Customer</h1>
            <p class="text-xs text-slate-500 mt-1">Book stays, packages, or activities directly for registered members or walk-in guests.</p>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-xs px-4 py-3 rounded-2xl mb-6 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <span class="material-symbols-outlined text-[18px]">error</span>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

     <form action="{{ route('admin.bookings.store') }}" method="POST" class="space-y-6" @submit="if(items.length === 0){ alert('Please add at least one item to the booking.'); $event.preventDefault(); } else { submitting = true; }">
        @csrf

        {{-- Hidden fields for items --}}
        <template x-for="(item, idx) in items" :key="idx">
            <div>
                <input type="hidden" :name="`items[${idx}][item_type]`" :value="item.item_type">
                <input type="hidden" :name="`items[${idx}][item_id]`" :value="item.item_id">
                <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.quantity">
                <input type="hidden" :name="`items[${idx}][selected_pax]`" :value="item.selected_pax">
                <input type="hidden" :name="`items[${idx}][check_in_date]`" :value="item.check_in_date || ''">
                <input type="hidden" :name="`items[${idx}][check_out_date]`" :value="item.check_out_date || ''">
            </div>
        </template>

        {{-- Hidden fields for passenger manifest --}}
        <template x-for="(pax, idx) in passengers" :key="idx">
            <div>
                <input type="hidden" :name="`guest_manifest[${idx}][full_name]`" :value="pax.full_name">
                <input type="hidden" :name="`guest_manifest[${idx}][category]`" :value="pax.category">
            </div>
        </template>

        <input type="hidden" name="user_id" :value="!isWalkIn && selectedUser ? selectedUser.id : ''">
        <input type="hidden" name="is_walk_in" :value="isWalkIn ? 1 : 0">

        {{-- Step 1: Customer Profile & Channel --}}
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-700 flex items-center justify-center font-bold text-xs">1</span>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 font-headline">Customer Information & Channel</h2>
                </div>

                {{-- Walk-in vs Registered Switcher --}}
                <div class="flex items-center bg-slate-100 p-1 rounded-xl text-xs font-semibold">
                    <button type="button" @click="isWalkIn = false"
                            :class="!isWalkIn ? 'bg-white text-ocean-700 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                            class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">person</span>
                        <span>Registered User</span>
                    </button>
                    <button type="button" @click="isWalkIn = true; clearSelectedUser();"
                            :class="isWalkIn ? 'bg-white text-ocean-700 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                            class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">person_outline</span>
                        <span>Walk-in / Phone Guest</span>
                    </button>
                </div>
            </div>

            {{-- Registered User Selection --}}
            <div x-show="!isWalkIn" class="space-y-4">
                <div x-show="!selectedUser" class="relative">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Search Registered Member *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" x-model="userSearch" @input="searchUsers()" placeholder="Search by member name, email, or phone number..."
                               class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                        <span x-show="isSearchingUser" class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400 font-medium animate-pulse">Searching...</span>
                    </div>

                    {{-- Search Dropdown Results --}}
                    <div x-show="userSearchResults.length > 0" @click.outside="userSearchResults = []"
                         class="absolute z-20 top-full left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-xl max-h-60 overflow-y-auto divide-y divide-slate-100">
                        <template x-for="u in userSearchResults" :key="u.id">
                            <button type="button" @click="selectUser(u)"
                                    class="w-full px-4 py-3 text-left hover:bg-slate-50 flex items-center justify-between transition-colors">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-ocean-50 text-ocean-700 font-bold flex items-center justify-center text-xs border border-ocean-200"
                                         x-text="u.name.substring(0,2).toUpperCase()"></div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900" x-text="u.name"></div>
                                        <div class="text-[11px] text-slate-500" x-text="u.email"></div>
                                    </div>
                                </div>
                                <div class="text-[11px] text-slate-600 font-mono" x-text="u.phone_number || 'No phone'"></div>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Selected User Banner --}}
                <div x-show="selectedUser" class="bg-ocean-50/60 border border-ocean-200 rounded-2xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-ocean-600 text-white font-bold flex items-center justify-center text-sm shadow-xs"
                             x-text="selectedUser ? selectedUser.name.substring(0,2).toUpperCase() : ''"></div>
                        <div>
                            <div class="text-xs font-bold text-slate-900 flex items-center gap-2">
                                <span x-text="selectedUser ? selectedUser.name : ''"></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-ocean-100 text-ocean-700">Verified Member</span>
                            </div>
                            <div class="text-[11px] text-slate-600 flex items-center gap-3 mt-0.5">
                                <span x-text="selectedUser ? selectedUser.email : ''"></span>
                                <span x-show="selectedUser && selectedUser.phone_number" class="text-slate-400">•</span>
                                <span x-text="selectedUser ? selectedUser.phone_number : ''"></span>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="clearSelectedUser()" class="text-xs font-bold text-rose-600 hover:underline px-3 py-1.5 rounded-lg hover:bg-rose-50 transition-colors">
                        Change Member
                    </button>
                </div>
            </div>

            {{-- Contact Inputs --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Lead Guest Name *</label>
                    <input type="text" name="contact_name" x-model="contactName" required placeholder="e.g. Maria Santos"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Contact Email *</label>
                    <input type="email" name="contact_email" x-model="contactEmail" required placeholder="e.g. maria@example.com"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Contact Phone Number *</label>
                    <input type="text" name="contact_phone" x-model="contactPhone" required placeholder="e.g. 0917 123 4567"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                </div>
            </div>

            {{-- Channel & Walk-in Account Option --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Booking Channel / Source *</label>
                    <select name="booking_source" x-model="bookingSource" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                        <option value="admin_walk_in">🏢 Walk-in Guest (Front Desk)</option>
                        <option value="admin_phone">📞 Phone Reservation (Call In)</option>
                        <option value="admin_concierge">🛎️ Concierge / VIP Custom Booking</option>
                    </select>
                </div>

                {{-- Walk-in Account Checkbox & Password Setting --}}
                <div x-show="isWalkIn" class="sm:col-span-2 pt-4 border-t border-slate-100 space-y-3">
                    <label class="relative flex items-start gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="auto_create_account" value="1" x-model="autoCreateAccount"
                               class="mt-0.5 rounded border-slate-300 text-ocean-600 focus:ring-ocean-500 w-4 h-4">
                        <div class="text-xs">
                            <span class="font-bold text-slate-900">Auto-create user account & email credentials</span>
                            <p class="text-[11px] text-slate-500">A new member account will be generated for this guest and a welcome email sent with login instructions.</p>
                        </div>
                    </label>

                    {{-- Visible Temporary Password Input when Checked --}}
                    <div x-show="autoCreateAccount" x-cloak class="bg-ocean-50/60 border border-ocean-200 rounded-2xl p-4 space-y-2.5 max-w-xl">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold uppercase tracking-wider text-ocean-900">
                                Temporary Account Password
                            </label>
                            <button type="button" @click="generatePassword()" class="text-[11px] font-bold text-ocean-700 hover:text-ocean-900 hover:underline flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]">refresh</span>
                                <span>Generate New</span>
                            </button>
                        </div>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'"
                                   name="temporary_password"
                                   x-model="temporaryPassword"
                                   placeholder="e.g. Sunny2026!"
                                   class="w-full pl-3.5 pr-20 py-2.5 rounded-xl border border-ocean-200 text-xs font-mono font-bold text-slate-900 bg-white focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 text-xs font-semibold flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[16px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                                <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            You can customize this password or keep the generated one. <strong>You will also see this password on the confirmation screen</strong> so you can provide it directly to the customer.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2: Selected Items (Trip Basket Builder) --}}
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-700 flex items-center justify-center font-bold text-xs">2</span>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 font-headline">Trip Items & Reservations</h2>
                </div>

                {{-- Add Item Buttons --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" @click="openAddModal('room')" class="px-3 py-1.5 rounded-xl bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100 text-xs font-bold flex items-center gap-1.5 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">hotel</span>
                        <span>+ Hotel Room</span>
                    </button>
                    <button type="button" @click="openAddModal('package')" class="px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 text-xs font-bold flex items-center gap-1.5 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">travel</span>
                        <span>+ Tour Package</span>
                    </button>
                    <button type="button" @click="openAddModal('activity')" class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 text-xs font-bold flex items-center gap-1.5 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">kitesurfing</span>
                        <span>+ Island Activity</span>
                    </button>
                    <button type="button" @click="openAddModal('addon')" class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 text-xs font-bold flex items-center gap-1.5 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">add_circle</span>
                        <span>+ Add-on</span>
                    </button>
                </div>
            </div>

            {{-- Empty Items State --}}
            <div x-show="items.length === 0" class="py-10 text-center rounded-2xl border-2 border-dashed border-slate-200 text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">shopping_bag</span>
                <p class="text-xs font-bold text-slate-600">No reservation items added yet.</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Use the buttons above to add rooms, tour packages, or activities to this booking.</p>
            </div>

            {{-- Items Table --}}
            <div x-show="items.length > 0" class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                        <tr>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Item Details</th>
                            <th class="py-2.5 px-3 text-center">Dates / Schedule</th>
                            <th class="py-2.5 px-3 text-center">Guests (Pax)</th>
                            <th class="py-2.5 px-3 text-center">Qty</th>
                            <th class="py-2.5 px-3 text-right">Unit Rate</th>
                            <th class="py-2.5 px-3 text-right">Subtotal</th>
                            <th class="py-2.5 px-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <template x-for="(item, idx) in items" :key="idx">
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase"
                                          :class="{
                                              'bg-sky-100 text-sky-700': item.item_type === 'room',
                                              'bg-indigo-100 text-indigo-700': item.item_type === 'package',
                                              'bg-emerald-100 text-emerald-700': item.item_type === 'activity',
                                              'bg-amber-100 text-amber-800': item.item_type === 'addon'
                                          }"
                                          x-text="item.item_type"></span>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900" x-text="item.title"></div>
                                    <div class="text-[11px] text-slate-500" x-text="item.subtitle"></div>
                                </td>
                                <td class="py-3 px-3 text-center text-[11px] text-slate-600 whitespace-nowrap">
                                    <span x-show="item.check_in_date" x-text="`${item.check_in_date} to ${item.check_out_date}`"></span>
                                    <span x-show="!item.check_in_date" class="text-slate-400">—</span>
                                </td>
                                <td class="py-3 px-3 text-center font-semibold" x-text="item.selected_pax"></td>
                                <td class="py-3 px-3 text-center font-semibold" x-text="item.quantity"></td>
                                <td class="py-3 px-3 text-right font-mono text-slate-700" x-text="'₱' + Number(item.unit_price).toLocaleString('en-US', {minimumFractionDigits: 2})"></td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" x-text="'₱' + Number(item.subtotal).toLocaleString('en-US', {minimumFractionDigits: 2})"></td>
                                <td class="py-3 px-3 text-center">
                                    <button type="button" @click="removeItem(idx)" class="text-rose-500 hover:text-rose-700 p-1 rounded hover:bg-rose-50 transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Step 3: Passenger Manifest & Category Rules --}}
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-700 flex items-center justify-center font-bold text-xs">3</span>
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 font-headline">Passenger Manifest & Category Rules</h2>
                        <p class="text-[11px] text-slate-400">Senior, PWD, and Child statutory adjustments are auto-calculated from active rules.</p>
                    </div>
                </div>
                <button type="button" @click="addPassenger()" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center gap-1 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">person_add</span>
                    <span>+ Add Passenger</span>
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(pax, idx) in passengers" :key="idx">
                    <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-200/60">
                        <span class="text-xs font-bold text-slate-400 w-6 text-center" x-text="idx + 1"></span>
                        <div class="flex-1">
                            <input type="text" x-model="pax.full_name" placeholder="Passenger Full Name" required
                                   class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white">
                        </div>
                        <div class="w-48">
                            <select x-model="pax.category" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white">
                                <template x-if="categoryRules.length === 0">
                                    <option value="Adult">Adult (Standard)</option>
                                </template>
                                <template x-for="r in categoryRules" :key="r.category_name">
                                    <option :value="r.category_name" x-text="r.display_label || r.category_name"></option>
                                </template>
                            </select>
                        </div>
                        <button type="button" @click="removePassenger(idx)" class="text-rose-500 hover:text-rose-700 p-1">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                </template>
                <div x-show="passengers.length === 0" class="text-center py-4 text-xs text-slate-400">
                    No individual passenger names entered. The lead guest contact name will be used by default.
                </div>
            </div>
        </div>

        {{-- Step 4: Pricing & Admin Adjustments --}}
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-4">
                <span class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-700 flex items-center justify-center font-bold text-xs">4</span>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 font-headline">Pricing Summary & Admin Adjustments</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Price Adjustments Inputs --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-emerald-700 mb-1.5">Admin Discount (₱)</label>
                            <input type="number" step="0.01" min="0" name="admin_discount_amount" x-model="adminDiscount" placeholder="0.00"
                                   class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-amber-700 mb-1.5">Admin Surcharge (₱)</label>
                            <input type="number" step="0.01" min="0" name="admin_surcharge_amount" x-model="adminSurcharge" placeholder="0.00"
                                   class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
                        </div>
                    </div>

                    <div x-show="parseFloat(adminDiscount) > 0 || parseFloat(adminSurcharge) > 0">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Reason for Price Adjustment *</label>
                        <input type="text" name="price_adjustment_reason" x-model="adjustmentReason" placeholder="e.g. VIP Member Courtesy Discount / Corporate Group"
                               :required="parseFloat(adminDiscount) > 0 || parseFloat(adminSurcharge) > 0"
                               class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Special Requests (Customer Note)</label>
                        <textarea name="special_requests" x-model="specialRequests" rows="2" placeholder="e.g. High floor, early check-in, dietary preferences..."
                                  class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Internal Admin Notes (Staff Only)</label>
                        <textarea name="admin_notes" x-model="adminNotes" rows="2" placeholder="e.g. Customer called front desk; paid via GCash to desk phone..."
                                  class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-ocean-500 focus:ring-2 focus:ring-ocean-500/20"></textarea>
                    </div>
                </div>

                {{-- Live Financial Summary Card --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-3 font-medium text-xs">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Gross Items Subtotal:</span>
                        <span class="font-mono font-bold text-slate-900" x-text="'₱' + Number(grossTotal).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>

                    <div class="flex items-center justify-between text-slate-600" x-show="passengerAdjustmentTotal !== 0">
                        <span>Passenger Category Adjustments:</span>
                        <span class="font-mono font-bold"
                              :class="passengerAdjustmentTotal < 0 ? 'text-emerald-700' : 'text-amber-700'"
                              x-text="(passengerAdjustmentTotal < 0 ? '−₱' : '+₱') + Number(Math.abs(passengerAdjustmentTotal)).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>

                    <div class="flex items-center justify-between text-emerald-700" x-show="parseFloat(adminDiscount) > 0">
                        <span>Admin Discount:</span>
                        <span class="font-mono font-bold" x-text="'−₱' + Number(adminDiscount).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>

                    <div class="flex items-center justify-between text-amber-700" x-show="parseFloat(adminSurcharge) > 0">
                        <span>Admin Surcharge:</span>
                        <span class="font-mono font-bold" x-text="'+₱' + Number(adminSurcharge).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>

                    <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-sm">
                        <span class="font-bold text-slate-900 uppercase">Total Net Amount:</span>
                        <span class="font-mono font-bold text-lg text-ocean-700" x-text="'₱' + Number(netTotal).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 5: Initial Status & Action --}}
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-4">
                <span class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-700 flex items-center justify-center font-bold text-xs">5</span>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 font-headline">Initial Booking Status & Payment</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Mode A: Pending --}}
                <label class="relative p-4 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                       :class="initialStatus === 'pending' ? 'border-amber-500 bg-amber-50/40' : 'border-slate-200 hover:border-slate-300'">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-amber-600 text-[18px]">pending</span>
                            <span>Save as Pending</span>
                        </div>
                        <input type="radio" name="initial_status" value="pending" x-model="initialStatus" class="text-ocean-600">
                    </div>
                    <p class="text-[11px] text-slate-500">Places booking in the review queue. Requires admin approval before payment.</p>
                </label>

                {{-- Mode B: Approved --}}
                <label class="relative p-4 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                       :class="initialStatus === 'approved' ? 'border-sky-500 bg-sky-50/40' : 'border-slate-200 hover:border-slate-300'">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sky-600 text-[18px]">check_circle</span>
                            <span>Save & Approve Immediately</span>
                        </div>
                        <input type="radio" name="initial_status" value="approved" x-model="initialStatus" class="text-ocean-600">
                    </div>
                    <p class="text-[11px] text-slate-500">Approves booking immediately. Sends customer an email with the 48h payment window and online payment link.</p>
                </label>

                {{-- Mode C: Paid --}}
                <label class="relative p-4 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                       :class="initialStatus === 'paid' ? 'border-emerald-500 bg-emerald-50/40' : 'border-slate-200 hover:border-slate-300'">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-emerald-600 text-[18px]">payments</span>
                            <span>Save & Mark as Paid</span>
                        </div>
                        <input type="radio" name="initial_status" value="paid" x-model="initialStatus" class="text-ocean-600">
                    </div>
                    <p class="text-[11px] text-slate-500">For over-the-counter payments (Cash, POS Terminal, Bank transfer). Issues official booking voucher right away.</p>
                </label>
            </div>

            {{-- Paid details dropdown --}}
            <div x-show="initialStatus === 'paid'" class="bg-emerald-50/60 border border-emerald-200 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-emerald-800 mb-1.5">Payment Method *</label>
                    <select name="payment_method" x-model="paymentMethod" class="w-full px-4 py-2.5 rounded-xl border border-emerald-200 text-xs font-semibold text-slate-900 bg-white">
                        <option value="Cash">💵 Cash (Over the counter)</option>
                        <option value="Credit Card POS">💳 Credit / Debit Card (POS Swiped)</option>
                        <option value="Bank Transfer">🏦 Bank Transfer (Direct deposit verified)</option>
                        <option value="GCash / Maya (Manual)">📱 GCash / Maya (Direct QR received)</option>
                        <option value="Complimentary">🎁 Complimentary / Sponsor</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-emerald-800 mb-1.5">Payment Reference / Receipt #</label>
                    <input type="text" name="payment_reference" x-model="paymentReference" placeholder="e.g. OR# 98421 or Bank Txn Ref"
                           class="w-full px-4 py-2.5 rounded-xl border border-emerald-200 text-xs font-medium text-slate-900 bg-white">
                </div>
            </div>

            {{-- Submit Action Bar --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.bookings.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs transition-colors">
                    Cancel
                </a>
                <button type="submit" :disabled="submitting" :class="submitting ? 'opacity-60 pointer-events-none' : ''" class="px-6 py-2.5 rounded-xl bg-ocean-600 hover:bg-ocean-700 text-white font-bold text-xs shadow-md transition-all flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]" x-text="submitting ? 'hourglass_top' : 'save'"></span>
                    <span x-text="submitting ? 'Creating…' : 'Confirm & Create Booking'"></span>
                </button>
            </div>
        </div>
    </form>

    {{-- Add Item Modal --}}
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="showAddModal = false" class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider font-headline" x-text="`Add ${modalType.toUpperCase()} Item`"></h3>
                <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="space-y-4 text-xs">
                {{-- Destination selector --}}
                <div>
                    <label class="block font-bold uppercase text-slate-700 mb-1">Destination *</label>
                    <select x-model="modalDestId" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-semibold">
                        <template x-for="d in destinations" :key="d.id">
                            <option :value="d.id" x-text="d.name"></option>
                        </template>
                    </select>
                </div>

                {{-- ROOM specific selectors --}}
                <template x-if="modalType === 'room'">
                    <div class="space-y-4">
                        <div>
                            <label class="block font-bold uppercase text-slate-700 mb-1">Hotel Property *</label>
                            <select x-model="modalHotelId" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-semibold">
                                <option value="">Select Hotel</option>
                                <template x-for="h in currentHotels" :key="h.id">
                                    <option :value="h.id" x-text="h.hotel_name || h.name"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold uppercase text-slate-700 mb-1">Room Type *</label>
                            <select x-model="modalRoomId" @change="checkRoomAvail()" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-semibold">
                                <option value="">Select Room Type</option>
                                <template x-for="r in currentRooms" :key="r.id">
                                    <option :value="r.id" x-text="`${r.room_name} (Base ₱${Number(r.base_price).toLocaleString('en-US')} / night)`"></option>
                                </template>
                            </select>
                            <div x-show="selectedModalRoom" class="mt-2 bg-slate-50 border border-slate-200/70 rounded-xl px-3 py-2.5 space-y-2">
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-slate-200 text-[11px] font-bold text-slate-700">
                                        <span class="material-symbols-outlined text-[14px] text-slate-500">group</span>
                                        <span>Sleeps up to <span class="text-slate-900" x-text="selectedModalRoom?.max_occupancy ?? '—'"></span></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-ocean-50 border border-ocean-200 text-[11px] font-bold text-ocean-800">
                                        <span class="material-symbols-outlined text-[14px] text-ocean-600">person_check</span>
                                        <span><span class="text-ocean-900" x-text="selectedModalRoom?.base_occupancy ?? '—'"></span> pax included in base</span>
                                    </span>
                                </div>
                                <div x-show="parseFloat(selectedModalRoom?.extra_person_fee) > 0" class="flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
                                    <span class="material-symbols-outlined text-[14px] text-slate-400">payments</span>
                                    <span>Extra guest: <span class="font-bold text-slate-700" x-text="`₱${Number(selectedModalRoom?.extra_person_fee).toLocaleString('en-US')}/pax/night`"></span> beyond base</span>
                                </div>
                                <div x-show="modalRoomExtraHeads.extra > 0" class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[11px]" x-text="`+₱${(modalRoomExtraHeads.extra * modalRoomExtraHeads.fee * itemNightsPreview).toLocaleString('en-US', {minimumFractionDigits: 2})} extra fee (${modalRoomExtraHeads.extra} × ₱${Number(modalRoomExtraHeads.fee).toLocaleString('en-US')} × ${itemNightsPreview} night${itemNightsPreview > 1 ? 's' : ''})`"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold uppercase text-slate-700 mb-1">Check-in Date *</label>
                                <input type="date" x-model="modalCheckIn" :min="todayStr" @change="checkRoomAvail()" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold">
                            </div>
                            <div>
                                <label class="block font-bold uppercase text-slate-700 mb-1">Check-out Date *</label>
                                <input type="date" x-model="modalCheckOut" :min="minCheckOut" @change="checkRoomAvail()" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold">
                            </div>
                        </div>

                        {{-- Real-time availability indicator --}}
                        <div x-show="modalRoomId">
                            <span x-show="checkingAvail" class="text-slate-400 animate-pulse text-[11px]">Checking availability...</span>
                            <div x-show="availResult && !checkingAvail" class="mt-1">
                                <span x-show="availResult?.available" class="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full font-bold text-[11px] border border-emerald-200">
                                    <span class="material-symbols-outlined text-[14px]">check</span>
                                    <span x-text="`${availResult?.remaining} room(s) available for selected dates`"></span>
                                </span>
                                <span x-show="!availResult?.available" class="inline-flex items-center gap-1 text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full font-bold text-[11px] border border-rose-200">
                                    <span class="material-symbols-outlined text-[14px]">block</span>
                                    <span>No rooms available for these dates!</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- PACKAGE specific selectors --}}
                <template x-if="modalType === 'package'">
                    <div class="space-y-4">
                        <div>
                            <label class="block font-bold uppercase text-slate-700 mb-1">Tour Package *</label>
                            <select x-model="modalPkgId" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-semibold">
                                <option value="">Select Tour Package</option>
                                <template x-for="p in currentPackages" :key="p.id">
                                    <option :value="p.id" x-text="`${p.name} (₱${Number(p.price).toLocaleString('en-US')} / pax)`"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </template>

                {{-- ACTIVITY specific selectors --}}
                <template x-if="modalType === 'activity'">
                    <div class="space-y-4">
                        <div>
                            <label class="block font-bold uppercase text-slate-700 mb-1">Island Activity *</label>
                            <select x-model="modalActId" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-semibold">
                                <option value="">Select Activity</option>
                                <template x-for="a in currentActivities" :key="a.id">
                                    <option :value="a.id" x-text="`${a.activity_name} (${a.rate || '₱0'})`"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </template>

                {{-- ADDON specific selectors --}}
                <template x-if="modalType === 'addon'">
                    <div class="space-y-4">
                        <div>
                            <label class="block font-bold uppercase text-slate-700 mb-1">Add-on Service *</label>
                            <select x-model="modalAddonId" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-semibold">
                                <option value="">Select Add-on</option>
                                <template x-for="ad in currentAddOns" :key="ad.id">
                                    <option :value="ad.id" x-text="ad.name"></option>
                                </template>
                            </select>
                            <div x-show="selectedModalAddon && (selectedModalAddon.pricing_tiers || []).length > 0" class="mt-2 bg-slate-50 border border-slate-200/70 rounded-xl px-3 py-2 space-y-1">
                                <template x-for="(t, i) in (selectedModalAddon?.pricing_tiers || [])" :key="i">
                                    <div class="flex items-center justify-between text-[11px] font-semibold" :class="isTierMatched(t) ? 'text-ocean-700' : 'text-slate-500'">
                                        <span x-text="`${t.min_pax ?? 1}–${t.max_pax ?? '∞'} pax`"></span>
                                        <span x-text="`₱${Number(t.rate ?? t.rate_per_pax ?? 0).toLocaleString('en-US')}`"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Common pax and qty inputs --}}
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <div>
                        <label class="block font-bold uppercase text-slate-700 mb-1">Number of Guests (Pax) *</label>
                        <input type="number" min="1" max="50" :max="modalType === 'room' && selectedModalRoom?.max_occupancy ? selectedModalRoom.max_occupancy : 50" x-model="modalPax" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold">
                        <p x-show="modalType === 'room' && selectedModalRoom" class="mt-1 text-[11px] text-slate-500">Guests share one unit · max <span class="font-bold text-slate-700" x-text="selectedModalRoom?.max_occupancy ?? '—'"></span> per unit</p>
                    </div>
                    <div>
                        <label class="block font-bold uppercase text-slate-700 mb-1">Quantity (Units) *</label>
                        <input type="number" min="1" max="20" x-model="modalQty" :max="modalType === 'room' && availResult ? availResult.remaining : 20" :disabled="modalType === 'room' && availResult && availResult.remaining === 0" class="w-full px-3 py-2 rounded-xl border font-semibold" :class="modalType === 'room' && availResult && modalQty > availResult.remaining ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white'">
                        <p x-show="modalType === 'room' && availResult" class="mt-1 text-[11px] font-bold" :class="availResult.remaining === 0 ? 'text-rose-600' : (modalQty > availResult.remaining ? 'text-rose-600' : 'text-emerald-600')">
                            <span x-show="availResult.remaining === 0">Fully booked for these dates — pick different dates/room</span>
                            <span x-show="availResult.remaining > 0 && modalQty <= availResult.remaining" x-text="`${availResult.remaining} of ${availResult.total_rooms} unit(s) available`"></span>
                            <span x-show="availResult.remaining > 0 && modalQty > availResult.remaining" x-text="`Only ${availResult.remaining} left — reduce Quantity to ${availResult.remaining} (add Pax for more guests in one unit)`"></span>
                        </p>
                        <p x-show="modalType === 'room' && !availResult && modalRoomId" class="mt-1 text-[11px] text-slate-400">Checking availability…</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50">Cancel</button>
                <button type="button" @click="addItemFromModal()" :disabled="modalType === 'room' && availResult && modalQty > availResult.remaining" :class="(modalType === 'room' && availResult && modalQty > availResult.remaining) ? 'opacity-40 cursor-not-allowed bg-slate-400' : 'bg-ocean-600 hover:bg-ocean-700'" class="px-5 py-2 rounded-xl text-white font-bold text-xs shadow-xs">Add to Booking</button>
            </div>
        </div>
    </div>
</div>
@endsection
