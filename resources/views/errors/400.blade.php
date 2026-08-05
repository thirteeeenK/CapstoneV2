<x-error-page
    code="400"
    subtitle="Bad Request"
    title="That request didn't make sense"
    message="We couldn't make sense of what the browser sent — something in the request didn't match what we were expecting. Nothing was processed, so you're free to try again."
    icon="report"
    theme="amber"
    :actions="[['back'], ['home']]"
/>
