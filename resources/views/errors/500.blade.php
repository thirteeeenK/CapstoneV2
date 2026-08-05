<x-error-page
    code="500"
    subtitle="Internal Server Error"
    title="The crew hit rough waters"
    message="Something down in the engine room threw a wrench into the works. Our crew has already been pinged — give it another try in a moment, or head back to shore."
    icon="thunderstorm"
    theme="storm"
    :actions="[['reload', 'Try Again'], ['home']]"
/>
