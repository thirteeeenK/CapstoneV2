# Task Checklist — Booking Saving + Approval + Payment

- [x] 1. Migrations (bookings alter, booking_items alter, booking_status_history, notifications)
- [x] 2. Models (Booking, BookingItem, BookingStatusHistory)
- [x] 3. Services (BookingRequestService, RoomAvailabilityService, PaymentService + drivers)
- [x] 4. CheckoutController refactor + checkout view (remove payment UI)
- [x] 5. RoomAvailabilityController → use service
- [x] 6. BookingConfirmationController status-aware + lazy expiry + booking/show view rework + pay page
- [x] 7. BookingPaymentController (pay/cancel/rebook/simulator) + bookingRoute.php
- [x] 8. PaymentWebhookController + paymentWebhookRoute.php
- [x] 9. AdminBookingController + adminBookingRoute.php + admin views + sidebar link
- [x] 10. Notifications (6) + NotificationController + notifications view + table
- [x] 11. ExpireBookings command + schedule in routes/console.php
- [x] 12. config/services.php + .env.example
- [x] 13. routes/web.php requires
- [x] 14. Tests (BookingFlowTest)
- [x] 15. php artisan migrate + composer test + manual verification

Note: pre-existing Auth/Profile scaffold tests fail because CheckUserOnboarding redirects factory users to /onboarding (expected per AGENTS.md). BookingFlowTest: 9/9 passing.
