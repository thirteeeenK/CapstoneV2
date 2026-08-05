<x-error-page
    code="401"
    subtitle="Unauthorized"
    title="Sign in to continue"
    message="The crew needs to see your boarding pass before opening this page. Sign in and we'll bring you right back to where you were headed."
    icon="lock"
    theme="sky"
    :actions="[['signin'], ['home']]"
/>
