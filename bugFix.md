Fix Destination Discover Sanctuary Button Routing
Currently, the "Discover Sanctuary" button on the frontend destinations.blade.php points to the view-listings route, which is protected by the auth:admin middleware because it is intended to be the admin dashboard's hotel management page.

To resolve this issue and allow public visitors to browse destinations, hotels, and activities, we will create a dedicated frontend destination view page.

Proposed Changes
Controller
[NEW] app/Http/Controllers/DestinationShowController.php
Create a new public controller with a show($id) method.
Fetch the destination by ID, along with its available hotels and activities (where is_shown is true).
If the destination doesn't exist, return a 404.
Routes
[MODIFY] routes/destinationRoute.php
Add a new public route definition for viewing a destination's listings.
Route::get('/destinations/{id}', [DestinationShowController::class, 'show'])->name('destinations.show');
Views
[MODIFY] resources/views/components/frontend/destinations.blade.php
Update the "Discover Sanctuary" button link to point to route('destinations.show', $dest->id) instead of view-listings.
[NEW] resources/views/destination/show.blade.php
Create a public-facing layout extending layouts.app (or similar frontend layout) to display the destination's details.
Show a curated list of Hotels and Activities available at that destination.
Ensure the design follows modern, responsive aesthetics using Tailwind CSS, similar to existing frontend pages.
Verification Plan
Manual Verification
Go to the homepage as a logged-out user.
Click the "Discover Sanctuary" button on any destination.
Verify that the page loads correctly showing the destination's hotels and activities without prompting for an admin login.
