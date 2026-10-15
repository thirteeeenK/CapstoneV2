<?php

return [
    'business' => [
        'display_name' => env('APP_NAME', 'SunnyTrips'),
        'support_email' => 'sunnytrips01@gmail.com',
        'phone' => '09682447153',
        'address' => "Brgy. San Agustin,\nPili, Camarines Sur,\nPhilippines, 4418",
        'facebook_url' => '',
        'instagram_url' => '',
        'website_url' => '',
    ],
    'support' => [
        'accept_requests' => true,
        'enforce_hours' => false,
        'unavailable_message' => 'Our support team is unavailable right now. Please try during our office hours. SunnyBot can still help you.',
        'schedule' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], [
            'enabled' => true, 'all_day' => false, 'opens' => '08:00', 'closes' => '20:00',
        ]),
    ],
    'chatbot' => ['daily_limit' => 15, 'minute_limit' => 5],
    'notifications' => [
        'recipient_mode' => 'all',
        'admin_ids' => [],
        'booking_requested' => false,
        'payment_submitted' => false,
        'cancellation_requested' => true,
        'support_requested' => false,
    ],
];
