@props([
    'roomId' => null,
    'basePrice' => 0,
    'inputName' => 'stay_dates',
    'checkInName' => 'check_in_date',
    'checkOutName' => 'check_out_date',
])

<div x-data="dateRangePicker({{ json_encode(['roomId' => $roomId, 'basePrice' => (float)$basePrice]) }})" 
     x-init="initFlatpickr()"
     class="w-full space-y-3 font-body">
    
    {{-- Flatpickr Calendar Input Trigger --}}
    <div class="relative">
        <label class="block text-[11px] uppercase tracking-wider font-extrabold text-slate-500 mb-1 flex items-center justify-between">
            <span>Select Stay Dates (Check-in → Check-out)</span>
            <span x-show="nights > 0" class="text-sky-700 font-bold" x-text="nights + ' Night' + (nights > 1 ? 's' : '')"></span>
        </label>
        
        <div class="relative flex items-center">
            <span class="absolute left-3 text-sky-600 material-symbols-outlined text-[18px] pointer-events-none">calendar_month</span>
            <input x-ref="datepicker" 
                   type="text" 
                   placeholder="Click to pick check-in & check-out dates..." 
                   readonly
                   class="w-full pl-10 pr-10 py-2.5 bg-slate-50 hover:bg-slate-100/80 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 shadow-2xs focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition cursor-pointer">
            
            <button x-show="checkIn && checkOut" 
                    @click="clearDates()" 
                    type="button" 
                    class="absolute right-3 text-slate-400 hover:text-slate-600 transition p-1">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>

        <input type="hidden" name="{{ $checkInName }}" :value="checkIn">
        <input type="hidden" name="{{ $checkOutName }}" :value="checkOut">
    </div>

    {{-- Live Summary & Availability Status Badge --}}
    <div x-show="checkIn && checkOut" x-cloak class="p-3 bg-sky-50/70 border border-sky-200/80 rounded-xl text-xs space-y-2">
        <div class="flex items-center justify-between font-semibold text-slate-700">
            <span class="flex items-center gap-1.5 text-sky-900">
                <span class="material-symbols-outlined text-[16px] text-sky-600">date_range</span>
                <span x-text="formattedDateRange"></span>
            </span>
            <span class="font-black text-slate-900" x-text="formattedSubtotal"></span>
        </div>

        {{-- Availability Indicator --}}
        <div class="pt-1.5 border-t border-sky-200/60 flex items-center justify-between text-[11px]">
            <template x-if="checking">
                <span class="text-slate-500 flex items-center gap-1">
                    <span class="inline-block animate-spin w-3 h-3 border-2 border-sky-600 border-t-transparent rounded-full"></span>
                    <span>Checking room availability...</span>
                </span>
            </template>

            <template x-if="!checking && available">
                <span class="text-emerald-700 font-bold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span x-text="availabilityMessage"></span>
                </span>
            </template>

            <template x-if="!checking && !available && checkIn">
                <span class="text-rose-600 font-bold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span x-text="availabilityMessage"></span>
                </span>
            </template>
        </div>
    </div>
</div>

<script>
function dateRangePicker(config) {
    return {
        roomId: config.roomId,
        basePrice: config.basePrice || 0,
        picker: null,
        checkIn: '',
        checkOut: '',
        nights: 0,
        subtotal: 0,
        formattedSubtotal: '₱0.00',
        formattedDateRange: '',
        checking: false,
        available: true,
        availabilityMessage: '',

        initFlatpickr() {
            this.$nextTick(() => {
                if (typeof flatpickr === 'undefined') {
                    console.error('Flatpickr library is missing!');
                    return;
                }

                this.picker = flatpickr(this.$refs.datepicker, {
                    mode: 'range',
                    minDate: 'today',
                    dateFormat: 'Y-m-d',
                    showMonths: 1,
                    onChange: (selectedDates, dateStr, instance) => {
                        let start = null;
                        let end = null;

                        if (selectedDates.length === 2) {
                            start = selectedDates[0];
                            end = selectedDates[1];
                        } else if (selectedDates.length === 1) {
                            start = selectedDates[0];
                            end = new Date(start);
                            end.setDate(end.getDate() + 1);
                        }

                        if (start && end) {
                            this.checkIn = instance.formatDate(start, 'Y-m-d');
                            this.checkOut = instance.formatDate(end, 'Y-m-d');

                            const diffTime = Math.abs(end - start);
                            this.nights = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));

                            const startFormatted = instance.formatDate(start, 'M j');
                            const endFormatted = instance.formatDate(end, 'M j, Y');
                            this.formattedDateRange = `${startFormatted} – ${endFormatted} (${this.nights} ${this.nights === 1 ? 'Night' : 'Nights'})`;

                            const effectiveBasePrice = this.basePrice || (this.selectedRoom ? Number(this.selectedRoom.base_price) : (this.previewRoom ? Number(this.previewRoom.base_price) : 0));
                            this.subtotal = effectiveBasePrice * this.nights;
                            this.formattedSubtotal = '₱' + this.subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                            this.verifyAvailability();
                        } else {
                            this.checkIn = '';
                            this.checkOut = '';
                            this.nights = 0;
                            this.subtotal = 0;
                            this.formattedDateRange = '';
                            this.available = true;
                        }

                        // Dispatch event for parent components
                        this.$dispatch('date-range-changed', {
                            checkIn: this.checkIn,
                            checkOut: this.checkOut,
                            nights: this.nights,
                            subtotal: this.subtotal,
                            available: this.available
                        });
                    }
                });
            });
        },

        clearDates() {
            if (this.picker) {
                this.picker.clear();
            }
            this.checkIn = '';
            this.checkOut = '';
            this.nights = 0;
            this.subtotal = 0;
            this.formattedDateRange = '';
            this.available = true;
            this.$dispatch('date-range-changed', { checkIn: '', checkOut: '', nights: 0, subtotal: 0, available: true });
        },

        async verifyAvailability() {
            const effectiveRoomId = this.roomId || (this.selectedRoom ? this.selectedRoom.id : (this.previewRoom ? this.previewRoom.id : null));
            const effectiveBasePrice = this.basePrice || (this.selectedRoom ? Number(this.selectedRoom.base_price) : (this.previewRoom ? Number(this.previewRoom.base_price) : 0));

            if (!effectiveRoomId || !this.checkIn || !this.checkOut) return;
            this.checking = true;
            try {
                const res = await fetch(`/rooms/${effectiveRoomId}/availability?check_in=${this.checkIn}&check_out=${this.checkOut}`);
                const data = await res.json();
                if (data.success) {
                    this.available = data.available;
                    this.availabilityMessage = data.message;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                } else {
                    this.available = false;
                    this.availabilityMessage = data.message || 'Selected dates unavailable.';
                }
            } catch (err) {
                console.error('Error verifying availability:', err);
            } finally {
                this.checking = false;
            }
        }
    };
}
</script>
