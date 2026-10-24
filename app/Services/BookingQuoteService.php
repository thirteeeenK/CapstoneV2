<?php

namespace App\Services;

/**
 * The one adjustment order for a booking total, shared by the checkout
 * preview, booking submission, admin-created bookings and approval repricing:
 *
 *   item subtotal − passenger-category discounts + passenger surcharges
 *   − promotional (Lucky) discount − admin discount + admin surcharge
 *
 * A promotion never replaces the required passenger adjustments; each
 * adjustment keeps its own column on the booking.
 */
final class BookingQuoteService
{
    public static function net(float $subtotal, float $passengerDiscount = 0, float $passengerSurcharge = 0, float $promotionDiscount = 0, float $adminDiscount = 0, float $adminSurcharge = 0): float
    {
        return round(max(0.00, $subtotal - $passengerDiscount + $passengerSurcharge - $promotionDiscount - $adminDiscount + $adminSurcharge), 2);
    }

    /**
     * The amount still payable before the promotion and admin adjustments,
     * which a promotion may never take below the gateway minimum.
     */
    public static function payableBeforePromotionCents(float $subtotal, float $passengerDiscount = 0, float $passengerSurcharge = 0): int
    {
        return (int) round(max(0.00, $subtotal - $passengerDiscount + $passengerSurcharge) * 100);
    }
}
