@extends('layouts.admin')

@section('title', 'Add Room Information | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Add Room — {{ $hotel->hotel_name }}</h1>
                <p class="text-xs text-slate-500 mt-1">Fill in the room details, amenities, and pricing below.</p>
            </div>
            <a href="{{ route('manage-rooms', $hotel->id) }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Rooms
            </a>
        </div>
        {{-- Success Message --}}
        @if (session('success'))
            <div
                class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-md mb-6">
                <strong class="font-semibold flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">error</span>
                    Please complete the form:
                </strong>
                <ul class="list-disc ml-5 mt-1.5 text-xs text-rose-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ route('store-room', $hotel->id) }}" method="POST" enctype="multipart/form-data"
            class="grid grid-cols-12 gap-8 items-start">
            @csrf
            <!-- Management Form (Wide Column) -->
            <div class="col-span-8 space-y-6">
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <h2
                        class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                        General Room Information
                    </h2>
                    <div class="space-y-4">
                        <!-- Room Name -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Room
                                Name</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="room_name" type="text" placeholder="e.g., Deluxe Room"
                                value="{{ old('room_name') }}" />
                        </div>

                        <!-- View Type -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">View
                                Type</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="view_type" type="text" placeholder="e.g., Ocean View, Pool View, Garden View"
                                value="{{ old('view_type') }}" />
                        </div>

                        <!-- Ideal Guest -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Ideal
                                Guest</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="ideal_guest" type="text" placeholder="e.g., Couples, Honeymooners, Families, Solo Travelers"
                                value="{{ old('ideal_guest', old('ideal_for')) }}" />
                        </div>

                        <!-- Description -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Detailed Room
                                Description</label>
                            <textarea
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="description" rows="3"
                                placeholder="Describe the room layout, luxury features, and unique perks...">{{ old('description') }}</textarea>
                        </div>

                        <!-- Additional Notes for RAG Chatbot -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Additional Notes & Policies (RAG Context)</label>
                            <textarea
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="additional_notes" rows="2"
                                placeholder="e.g., If there is an additional guest, a ₱500 fee per person will be charged. Check-in after 2:00 PM.">{{ old('additional_notes') }}</textarea>
                            <p class="text-[11px] text-slate-400">Free-form notes about extra fees, policies, or restrictions retrieved by the AI chatbot.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Total Number
                                of Rooms</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="total_rooms" type="number" placeholder="e.g., 1" value="{{ old('total_rooms') }}" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Base Included Guests</label>
                                <input class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                       name="base_occupancy" type="number" min="1" value="{{ old('base_occupancy', 2) }}" placeholder="e.g., 2" />
                                <p class="text-[11px] text-slate-400">Number of guests included in the base rate.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Max Capacity (Max Pax)</label>
                                <input class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                       name="max_occupancy" name="occupancy" type="number" min="1" value="{{ old('max_occupancy', old('occupancy', 3)) }}" placeholder="e.g., 3" />
                                <p class="text-[11px] text-slate-400">Maximum allowed guests in this room.</p>
                            </div>
                        </div>

                        <input type="hidden" name="occupancy" :value="document.querySelector('[name=max_occupancy]') ? document.querySelector('[name=max_occupancy]').value : 3">

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Bed
                                Configuration</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="bed_configuration" type="text" placeholder="e.g., 2 King-Sized Bed"
                                value="{{ old('bed_configuration') }}" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Room
                                Size</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="room_size" type="text" placeholder="e.g., 495 sq.ft / 46 sqm. With 8 sqm balcony."
                                value="{{ old('room_size') }}" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Base Rate (₱ / night)</label>
                                <input class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                       name="base_price" type="number" step="0.01" min="0" placeholder="e.g., 6000.00" value="{{ old('base_price') }}" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Extra Person Fee (₱ / night)</label>
                                <input class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                       name="extra_person_fee" type="number" step="0.01" min="0" placeholder="e.g., 500.00" value="{{ old('extra_person_fee', 500.00) }}" />
                                <p class="text-[11px] text-slate-400">Nightly fee per guest exceeding base capacity.</p>
                            </div>
                        </div>

                        <!-- Room Amenities -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Room
                                Amenities</label>
                            <input type="hidden" name="room_amenities" id="amenitiesInput"
                                value="{{ old('room_amenities') }}">
                            <div id="amenitiesContainer"
                                class="flex flex-wrap items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-md min-h-[46px]">
                                <button type="button" id="addAmenityBtn"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-300 hover:border-primary text-slate-600 hover:text-primary rounded-full text-xs font-semibold transition-colors shadow-sm">
                                    <span class="material-symbols-outlined text-sm">add</span>
                                    Add Amenity
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Image Gallery -->
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    {{-- Hidden file input --}}
                    <input type="file" id="hotelImageUpload" name="images[]" multiple accept="image/*" class="hidden"
                        onchange="handleImagePreview(this)" />

                    <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-3">
                        <h2 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                            <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                            Image Gallery
                        </h2>
                        <button type="button" onclick="document.getElementById('hotelImageUpload').click()"
                            class="inline-flex items-center gap-2 h-9 px-3 rounded-md bg-white border border-primary/30 hover:border-primary text-primary hover:bg-primary/5 text-sm font-medium transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary group">
                            <span
                                class="material-symbols-outlined text-[18px] text-primary transition-colors">cloud_upload</span>
                            Upload Images
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-4" id="imagePreviewContainer">
                        {{-- Boxes 1-3 (for Previews) --}}
                        @for ($i = 0; $i < 3; $i++)
                            <div class="aspect-square rounded-md bg-slate-50 flex flex-col items-center justify-center border border-slate-200 hover:border-slate-300 hover:bg-slate-100/50 transition-colors cursor-pointer relative group preview-slot"
                                onclick="if(!event.target.closest('.remove-image-btn')) document.getElementById('hotelImageUpload').click()">

                                {{-- Default Content (Icon & Text) --}}
                                <div class="default-content flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-slate-400 text-3xl mb-1.5">image</span>
                                    <span
                                        class="text-xs text-slate-400 font-medium">{{ $i == 0 ? 'Main Image' : 'Image Slot' }}</span>
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
                        <div onclick="document.getElementById('hotelImageUpload').click()"
                            class="aspect-square rounded-md border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 hover:text-primary hover:border-primary/40 hover:bg-slate-50 transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-2xl mb-1">add_circle</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider">More Photos</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Desktop Save Button (Sticky Sidebar Card) -->
            <div class="hidden lg:block lg:col-span-4">
                <div class="sticky top-6 bg-white rounded-lg p-6 border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">Publishing</h3>
                    <p class="text-xs text-slate-500 mb-4 leading-relaxed">Review your changes and click the button below to
                        save the room details for this hotel.</p>

                    <!-- Public Visibility Toggle -->
                    <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1.5">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-xs font-bold text-slate-800">Show to Public Users</span>
                            <input type="checkbox" name="is_shown" value="1" @checked(old('is_shown', true)) class="w-4 h-4 text-ocean-600 rounded border-slate-300 focus:ring-ocean-500">
                        </label>
                        <p class="text-[11px] text-slate-500 leading-tight">If unchecked, this room type remains hidden from public website and AI search.</p>
                    </div>
                    <button type="submit"
                        class="w-full h-10 px-4 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Save Room Information
                    </button>
                </div>
            </div>

            <!-- Mobile Floating Save Button -->
            <div
                class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-t border-slate-200 p-4 lg:hidden">
                <button type="submit"
                    class="w-full h-11 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Save Room Information
                </button>
            </div>
        </form>
    </div>

    {{-- Amenity Add Script --}}
    <script>
        // Global Variable para sa ating 'basket' ng images
        let imageBasket = new DataTransfer();
        const fileInput = document.getElementById('hotelImageUpload');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewSlots = previewContainer.querySelectorAll('.preview-slot');

        // Function na tinatawag kapag nag-add ng image sa input
        function handleImagePreview(input) {
            if (input.files) {
                // 1. Idagdag ang mga bagong files sa basket natin
                for (let i = 0; i < input.files.length; i++) {
                    // Opsyonal: Maglagay ng limit, e.g., hanggang 10 images lang total
                    if (imageBasket.files.length < 10) {
                        imageBasket.items.add(input.files[i]);
                    } else {
                        alert("Maximum of 10 images allowed.");
                        break;
                    }
                }
            }

            // 2. I-sync ang input field sa laman ng basket
            syncInputFiles();

            // 3. I-render ang previews
            renderGallery();
        }

        // Function para i-sync ang native input files sa ating imageBasket
        function syncInputFiles() {
            fileInput.files = imageBasket.files;
        }

        // Function para burahin ang isang image base sa index nito
        function removeImage(indexToRemove) {
            const newBasket = new DataTransfer();
            const currentFiles = imageBasket.files;

            // Loop sa lahat ng files sa lumang basket
            for (let i = 0; i < currentFiles.length; i++) {
                // Kung hindi ito yung gusto nating burahin, ilipat sa bagong basket
                if (i !== indexToRemove) {
                    newBasket.items.add(currentFiles[i]);
                }
            }

            // Palitan ang lumang basket ng bagong basket
            imageBasket = newBasket;

            // I-sync ulit ang input at i-render ang gallery
            syncInputFiles();
            renderGallery();
        }

        // Main Function para i-update ang UI (Previews)
        function renderGallery() {
            const accumulatedFiles = imageBasket.files;

            // Loop sa 3 slots natin sa UI
            previewSlots.forEach((slot, index) => {
                const defaultContent = slot.querySelector('.default-content');
                const previewContent = slot.querySelector('.image-preview-content');
                const removeBtn = slot.querySelector('.remove-image-btn');

                // Kung may image para sa slot na 'to (base sa index)
                if (index < accumulatedFiles.length) {
                    const file = accumulatedFiles[index];
                    let reader = new FileReader();

                    reader.onload = function (e) {
                        // I-pakita ang preview content at itago ang default
                        defaultContent.classList.add('hidden');
                        previewContent.classList.remove('hidden');

                        // Ilagay ang image bilang background
                        previewContent.style.backgroundImage = `url('${e.target.result}')`;

                        // I-setup ang 'x' button para tawagin ang removeImage function
                        // Gumamit tayo ng onclick directly para madaling ipasa ang index
                        removeBtn.onclick = (event) => {
                            event.stopPropagation(); // Pigilan ang slot onclick na tumakbo
                            removeImage(index);
                        };
                    }
                    reader.readAsDataURL(file);
                }
                // Kung walang image para sa slot na 'to
                else {
                    // I-pakita ang default content at itago ang preview
                    defaultContent.classList.remove('hidden');
                    previewContent.classList.add('hidden');
                    previewContent.style.backgroundImage = '';
                    removeBtn.onclick = null; // Tanggalin ang event listener
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('amenitiesContainer');
            const addBtn = document.getElementById('addAmenityBtn');
            const hiddenInput = document.getElementById('amenitiesInput');
            let amenities = [];

            function syncHiddenInput() {
                if (hiddenInput) {
                    hiddenInput.value = amenities.join(',');
                }
            }

            function createTag(text) {
                const tag = document.createElement('span');
                tag.className =
                    'inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-xs font-semibold animate-[fadeIn_0.2s_ease]';
                tag.innerHTML = `
                        ${text}
                        <button type="button" class="hover:text-rose-500 transition-colors ml-0.5" title="Remove">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    `;
                tag.querySelector('button').addEventListener('click', function () {
                    amenities = amenities.filter(a => a !== text);
                    syncHiddenInput();
                    tag.remove();
                });
                return tag;
            }

            if (hiddenInput && hiddenInput.value) {
                const initial = hiddenInput.value.split(',').map(a => a.trim()).filter(a => a.length > 0);
                initial.forEach(item => {
                    if (!amenities.includes(item)) {
                        amenities.push(item);
                        if (container && addBtn) {
                            container.insertBefore(createTag(item), addBtn);
                        }
                    }
                });
                syncHiddenInput();
            }

            if (addBtn && container) {
                addBtn.addEventListener('click', function () {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'e.g., Free Wi-Fi';
                    input.className =
                        'px-3 py-1.5 bg-white border border-primary/30 rounded-full text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 w-36 transition-all';

                    container.insertBefore(input, addBtn);
                    input.focus();

                    function commitAmenity() {
                        const val = input.value.trim();
                        if (val && !amenities.includes(val)) {
                            amenities.push(val);
                            syncHiddenInput();
                            container.insertBefore(createTag(val), addBtn);
                        }
                        input.remove();
                    }

                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            commitAmenity();
                        } else if (e.key === 'Escape') {
                            input.remove();
                        }
                    });

                    input.addEventListener('blur', commitAmenity);
                });
            }
        });
    </script>

    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
@endsection