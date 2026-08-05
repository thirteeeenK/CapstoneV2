<x-error-page
    code="419"
    subtitle="Page Expired"
    title="Your session drifted away"
    message="This page sat quiet a little too long, so we expired your session to keep things safe. Refresh the page to pick up exactly where you left off."
    icon="hourglass_bottom"
    theme="amber"
    :actions="[['reload'], ['home']]"
/>
