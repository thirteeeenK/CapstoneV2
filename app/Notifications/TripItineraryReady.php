<?php

namespace App\Notifications;

use App\Models\TripItinerary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripItineraryReady extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed> */
    public array $snapshot;

    public int $itineraryId;

    public int $tripPlanId;

    /**
     * Create a new notification instance.
     */
    public function __construct(TripItinerary $itinerary)
    {
        $this->snapshot = $itinerary->snapshot;
        $this->itineraryId = $itinerary->id;
        $this->tripPlanId = $itinerary->trip_plan_id;
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $snapshot = $this->snapshot;
        $view = ($snapshot['kind'] ?? null) === 'chatbot_draft' ? 'trip-planner.draft-document' : 'trip-planner.document';
        $pdf = Pdf::loadView($view, ['itinerary' => $snapshot, 'reference' => 'Plan '.$this->tripPlanId])->setPaper('a4')->output();

        return (new MailMessage)->subject('Your SunnyTrips itinerary · '.$snapshot['destination'])
            ->greeting('Hi '.$notifiable->name.',')->line(($snapshot['kind'] ?? null) === 'chatbot_draft' ? 'Your draft itinerary is attached. Dates, routes and weather still need review before booking.' : 'Your selected stays and visits are arranged by day in the attached itinerary, with route and weather explanations.')
            ->line('This is a travel plan. Your booking status determines confirmed services; recheck provisional forecasts before departure.')
            ->action('Open your saved trip', route('trip-planner.index', ['plan' => $this->tripPlanId]))
            ->attachData($pdf, 'sunnytrips-itinerary.pdf', ['mime' => 'application/pdf']);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'itinerary_id' => $this->itineraryId,
        ];
    }
}
