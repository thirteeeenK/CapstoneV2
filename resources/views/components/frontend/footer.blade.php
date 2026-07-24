<footer class="w-full pt-20 pb-10 px-8 bg-slate-50 border-t border-slate-200/80 font-body reveal-on-scroll">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-12 lg:gap-16">

            <!-- Column 1: Brand details -->
            <div class="md:col-span-5 lg:col-span-4 space-y-4">
                <div class="text-3xl font-headline font-extrabold text-slate-900 tracking-tight">
                    {{ config('app.name', 'SunnyTrips') }}
                </div>
                <p class="text-slate-500 text-sm leading-relaxed max-w-sm">
                    Redefining tropical exploration through curated luxury and local intelligence. We are the modern navigator for the Philippine archipelago.
                </p>
            </div>

            <!-- Spacer for large screens -->
            <div class="hidden lg:block lg:col-span-1"></div>

            <!-- Column 2: Navigation -->
            <div class="md:col-span-2 lg:col-span-2">
                <h4 class="font-label text-slate-900 text-xs font-bold tracking-widest uppercase mb-6">JOURNEY</h4>
                <div class="flex flex-col gap-4 text-slate-500 text-sm font-medium">
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Curations</a>
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Navigators</a>
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Private Charters</a>
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Eco-Projects</a>
                </div>
            </div>

            <!-- Column 3: Concierge -->
            <div class="md:col-span-2 lg:col-span-2">
                <h4 class="font-label text-slate-900 text-xs font-bold tracking-widest uppercase mb-6">CONCIERGE</h4>
                <div class="flex flex-col gap-4 text-slate-500 text-sm font-medium">
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Help Center</a>
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Safe Travel</a>
                    <a href="#" class="hover:text-slate-900 transition-colors duration-150">Booking Policy</a>
                </div>
            </div>

            <!-- Column 4: Contact -->
            <div class="md:col-span-3 lg:col-span-3">
                <h4 class="font-label text-slate-900 text-xs font-bold tracking-widest uppercase mb-6">THE OFFICE</h4>
                <p class="text-slate-500 text-sm leading-relaxed font-medium">
                    Brgy. San Agustin,<br />
                    Pili, Camarines Sur<br />
                    Philippines, 4418
                </p>
            </div>
        </div>

        <hr class="border-slate-200 mt-16 mb-8" />

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-slate-400 text-xs font-medium">
            <p>© {{date('Y')}} {{ config('app.name', 'SunnyTrips') }}. All rights reserved. Made for the sun-seekers.</p>
            <div class="flex gap-6">
                <a href="#" class="hover:text-slate-600 transition-colors duration-150">Privacy Policy</a>
                <a href="#" class="hover:text-slate-600 transition-colors duration-150">Terms of Use</a>
                <a href="#" class="hover:text-slate-600 transition-colors duration-150">Cookie Settings</a>
            </div>
        </div>
    </div>
</footer>