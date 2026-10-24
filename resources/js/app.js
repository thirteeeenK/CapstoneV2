
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './thinking-orb';
import './preview-modal';
import tripPlanner from './trip-planner';
import plannerScheduling from './admin-planner-scheduling';
import adminLiveList from './admin-live-list';
import { displayChatMessages } from './chat-sections';
import { durationLabel, tripDays, tripEndDate } from './trip-duration';

window.Alpine = Alpine;
window.displayChatMessages = displayChatMessages;
window.tripDuration = { durationLabel, tripDays, tripEndDate };

Alpine.plugin(focus);
Alpine.data('tripPlanner', tripPlanner);
Alpine.data('plannerScheduling', plannerScheduling);
Alpine.data('adminLiveList', adminLiveList);

Alpine.data('dateRangePicker', (config = {}) => ({
    roomId: config.roomId,
    basePrice: config.basePrice || 0,
    storeRoom: !!config.storeRoom,
    picker: null,
    checkIn: '',
    checkOut: '',
    nights: 0,
    subtotal: 0,
    formattedSubtotal: '₱0.00',
    formattedDateRange: '',
    checking: false,
    available: false,
    availabilityMessage: 'Select a room to check availability',

    initFlatpickr() {
        this.$nextTick(() => {
            if (typeof flatpickr === 'undefined') {
                console.error('Flatpickr library is missing!');
                return;
            }

            this.picker = flatpickr(this.$refs.datepicker, {
                mode: 'range',
                minDate: config.minimumArrival,
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
                        // Calendar-day arithmetic: elapsed hours shift across timezones and DST.
                        // A stay tied to a trip length (config.nights) always spans exactly that many nights;
                        // a same-day pick becomes a one-night stay.
                        const nights = Number.isInteger(config.nights) && config.nights > 0 ? config.nights
                            : Math.max(1, (tripDays(this.checkIn, instance.formatDate(end, 'Y-m-d')) ?? 2) - 1);
                        this.checkOut = tripEndDate(this.checkIn, nights + 1);
                        end = new Date(this.checkOut + 'T00:00:00');
                        this.nights = nights;

                        const startFormatted = instance.formatDate(start, 'M j');
                        const endFormatted = instance.formatDate(end, 'M j, Y');
                        this.formattedDateRange = `${startFormatted} – ${endFormatted} (${this.nights} ${this.nights === 1 ? 'Night' : 'Nights'})`;

                        const effectiveBasePrice = this.effectiveBasePrice();
                        this.subtotal = effectiveBasePrice * this.nights;
                        this.formattedSubtotal = '₱' + this.subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        this.verifyAvailability();
                    } else {
                        this.checkIn = '';
                        this.checkOut = '';
                        this.nights = 0;
                        this.subtotal = 0;
                        this.formattedDateRange = '';
                        this.available = false;
                    }

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
        this.available = false;
        this.$dispatch('date-range-changed', { checkIn: '', checkOut: '', nights: 0, subtotal: 0, available: false });
    },

    effectiveRoomId() {
        if (this.roomId) return this.roomId;
        if (this.storeRoom && this.$store?.preview?.room) return this.$store.preview.room.id;
        return null;
    },

    effectiveBasePrice() {
        if (this.basePrice) return Number(this.basePrice) || 0;
        if (this.storeRoom && this.$store?.preview?.room) return Number(this.$store.preview.room.base_price) || 0;
        return 0;
    },

    async verifyAvailability() {
        const effectiveRoomId = this.effectiveRoomId();
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
}));

Alpine.start();
