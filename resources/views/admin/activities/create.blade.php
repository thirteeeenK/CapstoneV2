@extends('layouts.admin')

@section('title', 'Add New Activity | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Add New Activity</h1>
                <p class="text-xs text-slate-500 mt-1">Create an activity, set pricing and activity level, and generate its AI embedding vector.</p>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Activities
            </a>
        </div>

        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-xs px-4 py-3 rounded-md mb-6 space-y-1">
                <p class="font-bold">Please correct the errors below:</p>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.activities.store') }}" method="POST" enctype="multipart/form-data"
            class="grid grid-cols-12 gap-8 items-start">
            @csrf

            <!-- Management Form (Wide Column) -->
            <div class="col-span-8 space-y-6">
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                        General Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Destination Dropdown -->
                    <div class="space-y-1.5">
                        <label for="destination_id" class="block text-xs font-semibold text-slate-700">
                            Destination <span class="text-rose-500">*</span>
                        </label>
                        <select id="destination_id" name="destination_id" required
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <option value="">Select Destination...</option>
                            @foreach($destinations as $dest)
                                <option value="{{ $dest->id }}" {{ old('destination_id') == $dest->id ? 'selected' : '' }}>
                                    {{ $dest->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Activity Name -->
                    <div class="space-y-1.5">
                        <label for="activity_name" class="block text-xs font-semibold text-slate-700">
                            Activity Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="activity_name" name="activity_name" value="{{ old('activity_name') }}"
                            placeholder="e.g. Parasailing, Jet Ski (15 mins), Joiners Island Hopping" required
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Category -->
                    <div class="space-y-1.5">
                        <label for="category" class="block text-xs font-semibold text-slate-700">
                            Category <span class="text-rose-500">*</span>
                        </label>
                        <select id="category" name="category" required
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <option value="">Select Category...</option>
                            <option value="Island Hopping" {{ old('category') == 'Island Hopping' ? 'selected' : '' }}>Island Hopping</option>
                            <option value="Land Tour" {{ old('category') == 'Land Tour' ? 'selected' : '' }}>Land Tour</option>
                            <option value="Water Activity" {{ old('category') == 'Water Activity' ? 'selected' : '' }}>Water Activity</option>
                            <option value="Diving" {{ old('category') == 'Diving' ? 'selected' : '' }}>Diving</option>
                            <option value="Adventure" {{ old('category') == 'Adventure' ? 'selected' : '' }}>Adventure</option>
                            <option value="Sailing" {{ old('category') == 'Sailing' ? 'selected' : '' }}>Sailing</option>
                            <option value="Other" {{ old('category') == 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>

                    <!-- Activity Level -->
                    <div class="space-y-1.5">
                        <label for="activity_level" class="block text-xs font-semibold text-slate-700">
                            Activity Level <span class="text-rose-500">*</span>
                        </label>
                        <select id="activity_level" name="activity_level" required
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <option value="">Select Level...</option>
                            <option value="Relaxing" {{ old('activity_level') == 'Relaxing' ? 'selected' : '' }}>Relaxing - Leisurely activities with little effort</option>
                            <option value="Sightseeing" {{ old('activity_level') == 'Sightseeing' ? 'selected' : '' }}>Sightseeing - Guided tours & local landmarks</option>
                            <option value="Adventure" {{ old('activity_level') == 'Adventure' ? 'selected' : '' }}>Adventure - Moderate physical activity & excitement</option>
                            <option value="Extreme" {{ old('activity_level') == 'Extreme' ? 'selected' : '' }}>Extreme - High-adrenaline speed or heights</option>
                            <option value="Underwater" {{ old('activity_level') == 'Underwater' ? 'selected' : '' }}>Underwater - Beneath the water surface</option>
                        </select>
                    </div>

                    <!-- Rate -->
                    <div class="space-y-1.5">
                        <label for="rate" class="block text-xs font-semibold text-slate-700">
                            Rate / Pricing <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="rate" name="rate" value="{{ old('rate') }}"
                            placeholder="e.g. ₱850/person, ₱2,000 (1-6 pax), ₱200–₱300/person" required
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Duration -->
                    <div class="space-y-1.5">
                        <label for="duration" class="block text-xs font-semibold text-slate-700">
                            Duration
                        </label>
                        <input type="text" id="duration" name="duration" value="{{ old('duration') }}"
                            placeholder="e.g. 3 Hours, Half Day, Full Day, 15 Mins"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Capacity -->
                    <div class="space-y-1.5">
                        <label for="capacity" class="block text-xs font-semibold text-slate-700">
                            Group Capacity
                        </label>
                        <input type="text" id="capacity" name="capacity" value="{{ old('capacity') }}"
                            placeholder="e.g. 1-6 Pax, Max 10 Guests, Solo or Groups"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Ideal For -->
                    <div class="space-y-1.5">
                        <label for="ideal_for" class="block text-xs font-semibold text-slate-700">
                            Target Audience / Ideal Participants
                        </label>
                        <input type="text" id="ideal_for" name="ideal_for" value="{{ old('ideal_for') }}"
                            placeholder="e.g. Couples, Thrill Seekers, Families with Kids, Solo Explorers"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Requirements / Restrictions -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="requirements" class="block text-xs font-semibold text-slate-700">
                            Requirements or Restrictions
                        </label>
                        <textarea id="requirements" name="requirements" rows="2"
                            placeholder="e.g. Must be at least 12 years old; bring extra swim clothes and towels"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('requirements') }}</textarea>
                    </div>

                    <!-- Vibe Tags -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="vibe_tags" class="block text-xs font-semibold text-slate-700">
                            Experience Vibes / Tags (Comma-separated)
                        </label>
                        <input type="text" id="vibe_tags" name="vibe_tags" value="{{ old('vibe_tags') }}"
                            placeholder="e.g. Snorkeling, Island Hopping, Panoramic Views, Adrenaline"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Detailed Description -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="description" class="block text-xs font-semibold text-slate-700">
                            Detailed Experience Description
                        </label>
                        <textarea id="description" name="description" rows="3"
                            placeholder="Describe the experience in detail so the AI embedding model can accurately understand its vibe..."
                            class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('description') }}</textarea>
                    </div>

                    <!-- Inclusions -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 flex justify-between items-center">
                            Inclusions
                            <button type="button" onclick="addInclusion()" class="text-ocean-600 hover:text-ocean-700 font-bold">+ Add Item</button>
                        </label>
                        <div id="inclusions_container" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <input type="text" name="inclusions[]" placeholder="e.g. Island hopping boat tour" class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                            </div>
                        </div>
                    </div>

                    <!-- Exclusions -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 flex justify-between items-center">
                            Exclusions / Optional Add-ons
                            <button type="button" onclick="addExclusion()" class="text-ocean-600 hover:text-ocean-700 font-bold">+ Add Item</button>
                        </label>
                        <div id="exclusions_container" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <input type="text" name="exclusions[]" placeholder="e.g. Snorkeling fee - ₱100/person" class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                            </div>
                        </div>
                    </div>

                    <!-- Itinerary -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 flex justify-between items-center">
                            Itinerary
                            <button type="button" onclick="addItinerary()" class="text-ocean-600 hover:text-ocean-700 font-bold">+ Add Step</button>
                        </label>
                        <div id="itinerary_container" class="space-y-3">
                            <div class="flex flex-col gap-2 p-3 border border-slate-200 rounded-md bg-slate-50 relative">
                                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-rose-500 hover:text-rose-700"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                <input type="text" name="itinerary[0][title]" placeholder="Step Title (e.g. Puka Beach)" class="w-full bg-white border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <input type="text" name="itinerary[0][duration]" placeholder="Duration (e.g. 30-40 minutes)" class="w-full bg-white border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="notes" class="block text-xs font-semibold text-slate-700">
                            Additional Notes
                        </label>
                        <textarea id="notes" name="notes" rows="2"
                            placeholder="e.g. No Sharon policy (no sharing of meals)"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('notes') }}</textarea>
                    </div>

                    <!-- Latitude & Longitude -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">Latitude</label>
                            <input name="latitude" type="number" step="any" placeholder="e.g., 11.9674"
                                value="{{ old('latitude') }}"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">Longitude</label>
                            <input name="longitude" type="number" step="any" placeholder="e.g., 121.9246"
                                value="{{ old('longitude') }}"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                        </div>
                    </div>

                    </div>
                </section>

                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <div class="space-y-3 md:col-span-2 pt-2 border-t border-slate-100">
                        {{-- Hidden file input --}}
                        <input type="file" id="activityImageUpload" name="images[]" multiple accept="image/*" class="hidden"
                            onchange="handleImagePreview(this)" />

                        <div class="flex justify-between items-center">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                                Photo Gallery (Optional)
                            </label>
                            <button type="button" onclick="document.getElementById('activityImageUpload').click()"
                                class="inline-flex items-center gap-2 h-8 px-3 rounded-md bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 text-xs font-medium transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">cloud_upload</span>
                                Upload Images
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-3 max-w-md" id="imagePreviewContainer">
                            {{-- Boxes 1-3 (for Previews) --}}
                            @for ($i = 0; $i < 3; $i++)
                                <div class="aspect-square rounded-md bg-slate-50 flex flex-col items-center justify-center border border-slate-200 hover:border-slate-300 hover:bg-slate-100/50 transition-colors cursor-pointer relative group preview-slot"
                                    onclick="if(!event.target.closest('.remove-image-btn')) document.getElementById('activityImageUpload').click()">

                                    {{-- Default Content (Icon & Text) --}}
                                    <div class="default-content flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-slate-400 text-3xl mb-1.5">image</span>
                                        <span class="text-xs text-slate-400 font-medium">{{ $i == 0 ? 'Main Photo' : 'Photo Slot' }}</span>
                                    </div>

                                    {{-- Image Preview Container (Hidden by default) --}}
                                    <div class="image-preview-content absolute inset-0 rounded-md hidden bg-cover bg-center">
                                        {{-- 'x' Button --}}
                                        <button type="button"
                                            class="remove-image-btn absolute top-2 right-2 bg-white/80 backdrop-blur-sm text-rose-600 rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-white active:scale-95 shadow-sm"
                                            title="Remove Image">
                                            <span class="material-symbols-outlined text-sm font-bold">close</span>
                                        </button>
                                    </div>
                                </div>
                            @endfor

                            {{-- "More Photos" Box (Box 4) --}}
                            <div onclick="document.getElementById('activityImageUpload').click()"
                                class="aspect-square rounded-md border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 hover:text-ocean-600 hover:border-ocean-300 hover:bg-slate-50 transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-2xl mb-1">add_circle</span>
                                <span class="text-[10px] font-bold uppercase tracking-wider">More Photos</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Desktop Save Button (Sticky Sidebar Card) -->
            <div class="hidden lg:block lg:col-span-4">
                <div class="sticky top-6 bg-white rounded-lg p-6 border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">Publishing</h3>
                    <p class="text-xs text-slate-500 mb-4 leading-relaxed">Review your changes and click the button below to publish the new activity.</p>

                    <!-- Public Visibility Toggle -->
                    <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1.5">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-xs font-bold text-slate-800">Show to Public Users</span>
                            <input type="checkbox" name="is_shown" value="1" @checked(old('is_shown', true)) class="w-4 h-4 text-ocean-600 rounded border-slate-300 focus:ring-ocean-500">
                        </label>
                        <p class="text-[11px] text-slate-500 leading-tight">If unchecked, this activity remains hidden from public website and AI search.</p>
                    </div>
                    <button type="submit"
                        class="w-full h-10 px-4 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Save & Generate Embedding
                    </button>
                </div>
            </div>

            <!-- Mobile Floating Save Button -->
            <div class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-t border-slate-200 p-4 lg:hidden">
                <button type="submit"
                    class="w-full h-11 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Save Activity
                </button>
            </div>
        </form>
    </div>

    {{-- Instant Image Preview & Removal Script (Adapted from add_hotel.blade.php) --}}
    <script>
        let imageBasket = new DataTransfer();
        const fileInput = document.getElementById('activityImageUpload');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewSlots = previewContainer.querySelectorAll('.preview-slot');

        function handleImagePreview(input) {
            if (input.files) {
                for (let i = 0; i < input.files.length; i++) {
                    if (imageBasket.files.length < 10) {
                        imageBasket.items.add(input.files[i]);
                    } else {
                        alert("Maximum of 10 images allowed.");
                        break;
                    }
                }
            }
            syncInputFiles();
            renderGallery();
        }

        function syncInputFiles() {
            if (fileInput) {
                fileInput.files = imageBasket.files;
            }
        }

        function removeImage(indexToRemove) {
            const newBasket = new DataTransfer();
            const currentFiles = imageBasket.files;

            for (let i = 0; i < currentFiles.length; i++) {
                if (i !== indexToRemove) {
                    newBasket.items.add(currentFiles[i]);
                }
            }

            imageBasket = newBasket;
            syncInputFiles();
            renderGallery();
        }

        function renderGallery() {
            const accumulatedFiles = imageBasket.files;

            previewSlots.forEach((slot, index) => {
                const defaultContent = slot.querySelector('.default-content');
                const previewContent = slot.querySelector('.image-preview-content');
                const removeBtn = slot.querySelector('.remove-image-btn');

                if (index < accumulatedFiles.length) {
                    const file = accumulatedFiles[index];
                    let reader = new FileReader();

                    reader.onload = function (e) {
                        defaultContent.classList.add('hidden');
                        previewContent.classList.remove('hidden');
                        previewContent.style.backgroundImage = `url('${e.target.result}')`;

                        removeBtn.onclick = (event) => {
                            event.stopPropagation();
                            removeImage(index);
                        };
                    }
                    reader.readAsDataURL(file);
                } else {
                    defaultContent.classList.remove('hidden');
                    previewContent.classList.add('hidden');
                    previewContent.style.backgroundImage = '';
                    removeBtn.onclick = null;
                }
            });
        }

        // Dynamic fields for Inclusions, Exclusions, Itinerary
        let itineraryIndex = 1;

        function addInclusion() {
            const container = document.getElementById('inclusions_container');
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = `
                <input type="text" name="inclusions[]" placeholder="e.g. Island hopping boat tour" class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><span class="material-symbols-outlined text-[16px]">delete</span></button>
            `;
            container.appendChild(div);
        }

        function addExclusion() {
            const container = document.getElementById('exclusions_container');
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = `
                <input type="text" name="exclusions[]" placeholder="e.g. Snorkeling fee - ₱100/person" class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><span class="material-symbols-outlined text-[16px]">delete</span></button>
            `;
            container.appendChild(div);
        }

        function addItinerary() {
            const container = document.getElementById('itinerary_container');
            const div = document.createElement('div');
            div.className = 'flex flex-col gap-2 p-3 border border-slate-200 rounded-md bg-slate-50 relative';
            div.innerHTML = `
                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-rose-500 hover:text-rose-700"><span class="material-symbols-outlined text-[16px]">close</span></button>
                <input type="text" name="itinerary[${itineraryIndex}][title]" placeholder="Step Title (e.g. Puka Beach)" class="w-full bg-white border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                <input type="text" name="itinerary[${itineraryIndex}][duration]" placeholder="Duration (e.g. 30-40 minutes)" class="w-full bg-white border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
            `;
            container.appendChild(div);
            itineraryIndex++;
        }
    </script>
@endsection