import Alpine from 'alpinejs';

/**
 * Global preview store shared by the room & activity preview modals across
 * the hotel page, activity catalog, destination page, and chat widget.
 */
Alpine.store('preview', {
    room: null,
    activity: null,
    package: null,
    roomImgIndex: 0,
    actImgIndex: 0,
    pkgImgIndex: 0,
    pax: 1,
    activityPax: 1,
    roomDates: { checkIn: '', checkOut: '', nights: 0, available: true, availabilityMessage: '', subtotal: 0 },
    pickerEpoch: 0,
    loading: false,
    loadingActivity: false,
    loadingPackage: false,

    get computedNightlyRate() {
        const target = this.room;
        if (!target) return 0;
        const basePrice = Number(target.base_price) || 0;
        const basePax = Number(target.base_occupancy) || 2;
        const extraFee = Number(target.extra_person_fee) || 0;
        const pax = Number(this.pax) || basePax;
        if (pax > basePax) {
            return basePrice + ((pax - basePax) * extraFee);
        }
        return basePrice;
    },

    openRoom(payload) {
        this.room = payload;
        this.roomImgIndex = 0;
        this.pax = Number(payload.base_occupancy) || 2;
        this.roomDates = { checkIn: '', checkOut: '', nights: 0, available: true, availabilityMessage: '', subtotal: 0 };
        this.pickerEpoch++;
    },

    closeRoom() {
        this.room = null;
    },

    async openRoomById(id) {
        this.loading = true;
        try {
            const res = await fetch(`/rooms/${id}/preview`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('room not found');
            const data = await res.json();
            this.openRoom(data);
        } catch (err) {
            alert('This room is no longer available.');
        } finally {
            this.loading = false;
        }
    },

    setRoomDates(detail) {
        this.roomDates = {
            checkIn: detail.checkIn || '',
            checkOut: detail.checkOut || '',
            nights: detail.nights || 0,
            available: detail.available !== false,
            availabilityMessage: detail.availabilityMessage || '',
            subtotal: detail.subtotal || 0,
        };
    },

    addRoomToCart() {
        const room = this.room;
        if (!room) return;
        const { checkIn, checkOut, available } = this.roomDates;
        if (!checkIn || !checkOut) {
            alert('Please select your stay check-in and check-out dates first!');
            return;
        }
        if (!available) return;
        if (typeof window.addToCart === 'function') {
            window.addToCart('room', room.id, { check_in_date: checkIn, check_out_date: checkOut, selected_pax: this.pax });
        }
        this.closeRoom();
    },

    openActivity(payload) {
        this.activity = payload;
        this.actImgIndex = 0;
        this.activityPax = 1;
    },

    closeActivity() {
        this.activity = null;
    },

    async openActivityById(id) {
        this.loadingActivity = true;
        try {
            const res = await fetch(`/activities/${id}/preview`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('activity not found');
            const data = await res.json();
            this.openActivity(data);
        } catch (err) {
            alert('This activity is no longer available.');
        } finally {
            this.loadingActivity = false;
        }
    },

    addActivityToCart() {
        const act = this.activity;
        if (!act) return;
        if (typeof window.addToCart === 'function') {
            window.addToCart('activity', act.id, { selected_pax: this.activityPax });
        }
        this.closeActivity();
    },

    openPackage(payload) {
        this.package = payload;
        this.pkgImgIndex = 0;
    },

    closePackage() {
        this.package = null;
    },

    async openPackageById(id) {
        this.loadingPackage = true;
        try {
            const res = await fetch(`/packages/${id}/preview`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('package not found');
            const data = await res.json();
            this.openPackage(data);
        } catch (err) {
            alert('This package is no longer available.');
        } finally {
            this.loadingPackage = false;
        }
    },

    addPackageToCart() {
        const pkg = this.package;
        if (!pkg) return;
        if (typeof window.addToCart === 'function') {
            window.addToCart('package', pkg.id, { quantity: pkg.min_pax || 2, selected_pax: pkg.min_pax || 2 });
        }
        this.closePackage();
    },
});
