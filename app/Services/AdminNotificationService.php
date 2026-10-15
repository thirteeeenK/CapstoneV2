<?php

namespace App\Services;

use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\SupportInquiry;
use App\Notifications\AdminOperationalAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AdminNotificationService
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    public function send(string $type, Booking|SupportInquiry $subject): void
    {
        DB::afterCommit(function () use ($type, $subject): void {
            try {
                $preferences = $this->settings->section('notifications');
                if (! ($preferences[$type] ?? false)) {
                    return;
                }
                $query = AdminModel::query();
                if ($preferences['recipient_mode'] === 'selected') {
                    $query->whereIn('id', $preferences['admin_ids']);
                }
                $reference = $subject instanceof Booking ? $subject->booking_code : $subject->ticket_number;
                $url = $subject instanceof Booking ? route('admin.bookings.show', $subject->id) : route('admin.support.index');
                Notification::send($query->get(), new AdminOperationalAlert($type, $reference, $url));
            } catch (Throwable $exception) {
                Log::warning('admin.alert_dispatch_failed', [
                    'type' => $type, 'record_id' => $subject->id, 'exception_type' => $exception::class,
                ]);
            }
        });
    }
}
