@php
    $suspended = session('suspended');
    $message = 'This account is currently restricted from accessing the SunnyTrips booking platform. Please contact our support team if you believe this is a mistake.';

    if ($suspended) {
        if ($suspended['level'] === 'temporary') {
            $message = 'Your account is temporarily suspended. Access is restored on '
                . ($suspended['expires_at'] ?? 'a later date') . '.';

            if (!empty($suspended['reason'])) {
                $message .= ' Reason: ' . rtrim($suspended['reason'], " .") . '.';
            }
        } else {
            $message = 'Your account has been permanently banned.';

            if (!empty($suspended['banned_at'])) {
                $message .= ' Suspended on ' . $suspended['banned_at'] . '.';
            }

            if (!empty($suspended['reason'])) {
                $message .= ' Reason: ' . rtrim($suspended['reason'], " .") . '.';
            }
        }
    }
@endphp

<x-error-page
    code="SUS"
    subtitle="Account Restricted"
    title="Your account has been suspended"
    :message="$message"
    icon="block"
    theme="coral"
    :actions="[['home'], ['support', 'Contact Support']]"
/>
