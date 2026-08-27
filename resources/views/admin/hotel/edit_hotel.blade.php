@extends('layouts.admin')

@section('title', 'Edit Hotel Information | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Edit Hotel Information</h1>
                <p class="text-xs text-slate-500 mt-1">Update property details, amenities, and location settings.</p>
            </div>
            <a href="{{ route('view-listings') }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Listings
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

        <form action="{{ route('edit-information', $hotel->id) }}" method="POST" enctype="multipart/form-data"
            class="grid grid-cols-12 gap-8 items-start">
            @csrf
            @method('PUT')
            <!-- Management Form (Wide Column) -->
            <div class="col-span-8 space-y-6">
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <h2
                        class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                        General Information
                    </h2>
                    <div class="space-y-4">
                        <!-- Hotel Name -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Hotel
                                Name</label>
                            <input
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                name="hotel_name" type="text" placeholder="e.g., Henann Palm Beach Resort"
                                value="{{ old('hotel_name', $hotel->hotel_name ?? '') }}" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider" for="type">
                                Hotel Type
                            </label>

                            <div class="relative">
                                <select name="type" id="type"
                                    class="w-full appearance-none bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 pr-10 text-sm text-slate-900 shadow-sm transition duration-150 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    <option value="high-end-luxury-hotels" @selected(old('type') == 'high-end-luxury-hotels')>
                                        Luxury / High-End Hotels
                                    </option>
                                    <option value="mid-affordable-hotels" @selected(old('type') == 'mid-affordable-hotels')>
                                        Mid-Affordable Hotels
                                    </option>
                                </select>

                                <!-- Custom Dropdown Arrow -->
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Hotel Description -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Hotel
                                Description</label>
                            <textarea
                                class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors leading-relaxed"
                                name="hotel_description" rows="6"
                                placeholder="Describe the property, location, and unique features...">{{ $hotel->hotel_description ?? '' }}</textarea>
                        </div>

                        <!-- Destination & Specific Address -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Destination Dropdown -->
                            <div class="space-y-1.5">
                                <label
                                    class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Destination</label>
                                <div class="relative">
                                    <select name="destination_id"
                                        class="w-full appearance-none bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 pr-10 text-sm text-slate-900 shadow-sm transition duration-150 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                        <option value="" disabled {{ old('destination_id', $hotel->destination_id) ? '' : 'selected' }}>Select a destination...</option>

                                        @foreach($destinations as $destination)
                                            <option value="{{ $destination->id }}" @selected(old('destination_id', $hotel->destination_id) == $destination->id)>
                                                {{ $destination->name }}
                                            </option>
                                        @endforeach

                                    </select>
                                    <!-- Custom Dropdown Arrow -->
                                    <div
                                        class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Specific Address -->
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Specific
                                    Address</label>
                                <input
                                    class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                    name="specific_address" type="text" placeholder="e.g., Station 2, White Beach"
                                    value="{{ old('specific_address', $hotel->specific_address ?? '') }}" />
                            </div>

                            @php
                                $vibeStr = is_array($hotel->vibe_tags) ? implode(', ', $hotel->vibe_tags) : ($hotel->vibe_tags ?? '');
                                $amenitiesStr = is_array($hotel->featured_amenities) ? implode(', ', $hotel->featured_amenities) : ($hotel->featured_amenities ?? '');
                            @endphp

                            <!-- Vibe Tags -->
                            <div class="space-y-1.5 md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Hotel
                                    Vibes & Tags (Comma-separated)</label>
                                <input
                                    class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                    name="vibe_tags" type="text"
                                    placeholder="e.g., Luxury, Beachfront, Infinity Pool, Romantic, Quiet"
                                    value="{{ old('vibe_tags', $vibeStr) }}" />
                            </div>

                            <!-- Featured Amenities -->
                            <div class="space-y-1.5 md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Featured
                                    Amenities (Comma-separated)</label>
                                <input
                                    class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                    name="featured_amenities" type="text"
                                    placeholder="e.g., Infinity Pool, Swim-up Bar, Direct Beach Access, Spa, Fine Dining"
                                    value="{{ old('featured_amenities', $amenitiesStr) }}" />
                            </div>
                        </div>

                        <!-- Latitude & Longitude -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label
                                    class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Latitude</label>
                                <input
                                    class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                    name="latitude" type="number" step="any" placeholder="e.g., 11.9674"
                                    value="{{ old('latitude', $hotel->latitude ?? '') }}" />
                            </div>
                            <div class="space-y-1.5">
                                <label
                                    class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Longitude</label>
                                <input
                                    class="w-full bg-white border border-slate-300 hover:border-slate-400 rounded-md py-2 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                                    name="longitude" type="number" step="any" placeholder="e.g., 121.9246"
                                    value="{{ old('longitude', $hotel->longitude ?? '') }}" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Image Gallery -->
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    {{-- Container para sa mga images na gustong burahin ng user --}}
                    <div id="removedImagesContainer"></div>

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
                        {{-- Boxes 1-3 (for Previews - HTML Skeleton only, JS will fill this) --}}
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
                        update the hotel listing details.</p>

                    <!-- Public Visibility Toggle -->
                    <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1.5">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-xs font-bold text-slate-800">Show to Public Users</span>
                            <input type="checkbox" name="is_shown" value="1" @checked(old('is_shown', $hotel->is_shown ?? true)) class="w-4 h-4 text-ocean-600 rounded border-slate-300 focus:ring-ocean-500">
                        </label>
                        <p class="text-[11px] text-slate-500 leading-tight">If unchecked, this hotel remains hidden from public website and AI search.</p>
                    </div>
                    <button type="submit"
                        class="w-full h-10 px-4 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Update Information
                    </button>
                </div>
            </div>

            <!-- Mobile Floating Save Button -->
            <div
                class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-t border-slate-200 p-4 lg:hidden">
                <button type="submit"
                    class="w-full h-11 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Update Information
                </button>
            </div>
        </form>
    </div>

    {{-- Amenity Add Script --}}
    <script>
        // 1. Kunin ang mga existing images mula sa database (assumed na naka array or JSON sa DB)
        @php
            $rawImages = is_string($hotel->images) ? json_decode($hotel->images, true) : $hotel->images ?? [];
            $existingImagesData = [];
            if (is_array($rawImages)) {
                foreach ($rawImages as $img) {
                    $existingImagesData[] = [
                        'path' => $img, // Eto ipapasa natin sa controller pang-delete (e.g., 'hotels/img1.jpg')
                        'url' => App\Concerns\ResolvesImages::resolveImg($img), // Eto yung URL para ma-display sa UI
                    ];
                }
            }
        @endphp

        // State Variables
        let existingImages = @json($existingImagesData); // Galing sa DB
        let removedExistingPaths = []; // Dito mapupunta ang mga pinindot na 'x'
        let imageBasket = new DataTransfer(); // Para sa mga BAGONG upload

        const fileInput = document.getElementById('hotelImageUpload');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewSlots = previewContainer.querySelectorAll('.preview-slot');
        const removedImagesContainer = document.getElementById('removedImagesContainer');

        // Patakbuhin ang render sa pag-load ng page para lumabas ang dating images
        document.addEventListener('DOMContentLoaded', () => {
            renderGallery();
        });

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
            fileInput.files = imageBasket.files;
        }

        // Function para alisin ang BAGONG upload
        function removeNewImage(fileToRemove) {
            const newBasket = new DataTransfer();
            Array.from(imageBasket.files).forEach(file => {
                if (file !== fileToRemove) {
                    newBasket.items.add(file);
                }
            });
            imageBasket = newBasket;
            syncInputFiles();
            renderGallery();
        }

        // Function para markahan ang LUMANG image as removed
        function removeExistingImage(imagePath) {
            // Idagdag sa array ng mga tatanggalin
            removedExistingPaths.push(imagePath);

            // Gawa ng hidden input para alam ng Laravel backend
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_images[]';
            input.value = imagePath;
            removedImagesContainer.appendChild(input);

            // I-render ulit ang UI
            renderGallery();
        }

        // Main Function para i-update ang UI
        function renderGallery() {
            let displayItems = [];

            // Step A: Ilagay ang mga existing images na HINDI PA nabubura
            existingImages.forEach(img => {
                if (!removedExistingPaths.includes(img.path)) {
                    displayItems.push({
                        type: 'existing',
                        data: img
                    });
                }
            });

            // Step B: Ilagay sa dulo ang mga bagong in-upload na files
            Array.from(imageBasket.files).forEach(file => {
                displayItems.push({
                    type: 'new',
                    data: file
                });
            });

            // Step C: I-display sa UI slots (Boxes 1-3)
            previewSlots.forEach((slot, index) => {
                const defaultContent = slot.querySelector('.default-content');
                const previewContent = slot.querySelector('.image-preview-content');
                const removeBtn = slot.querySelector('.remove-image-btn');

                if (index < displayItems.length) {
                    const item = displayItems[index];

                    defaultContent.classList.add('hidden');
                    previewContent.classList.remove('hidden');

                    if (item.type === 'existing') {
                        // Render Luma
                        previewContent.style.backgroundImage = `url('${item.data.url}')`;
                        removeBtn.onclick = (event) => {
                            event.stopPropagation();
                            removeExistingImage(item.data.path);
                        };
                    } else {
                        // Render Bago
                        let reader = new FileReader();
                        reader.onload = function (e) {
                            previewContent.style.backgroundImage = `url('${e.target.result}')`;
                        }
                        reader.readAsDataURL(item.data);

                        removeBtn.onclick = (event) => {
                            event.stopPropagation();
                            removeNewImage(item.data);
                        };
                    }
                } else {
                    // Walang laman na slot
                    defaultContent.classList.remove('hidden');
                    previewContent.classList.add('hidden');
                    previewContent.style.backgroundImage = '';
                    removeBtn.onclick = null;
                }
            });
        }


        // document.addEventListener('DOMContentLoaded', function() {
        //     const container = document.getElementById('amenitiesContainer');
        //     const addBtn = document.getElementById('addAmenityBtn');
        //     const hiddenInput = document.getElementById('amenitiesInput');
        //     let amenities = [];

        //     function syncHiddenInput() {
        //         hiddenInput.value = amenities.join(',');
        //     }

        //     function createTag(text) {
        //         const tag = document.createElement('span');
        //         tag.className =
        //             'inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-xs font-semibold animate-[fadeIn_0.2s_ease]';
        //         tag.innerHTML = `
        //                                                             ${text}
        //                                                             <button type="button" class="hover:text-red-500 transition-colors ml-0.5" title="Remove">
        //                                                                 <span class="material-symbols-outlined text-sm">close</span>
        //                                                             </button>
        //                                                         `;
        //         tag.querySelector('button').addEventListener('click', function() {
        //             amenities = amenities.filter(a => a !== text);
        //             syncHiddenInput();
        //             tag.remove();
        //         });
        //         return tag;
        //     }

        //     addBtn.addEventListener('click', function() {
        //         // Replace button with an inline input
        //         const input = document.createElement('input');
        //         input.type = 'text';
        //         input.placeholder = 'e.g., Free Wi-Fi';
        //         input.className =
        //             'px-3 py-1.5 bg-surface-container-low border border-primary/30 rounded-full text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 w-36 transition-all';

        //         container.insertBefore(input, addBtn);
        //         input.focus();

        //         function commitAmenity() {
        //             const val = input.value.trim();
        //             if (val && !amenities.includes(val)) {
        //                 amenities.push(val);
        //                 syncHiddenInput();
        //                 container.insertBefore(createTag(val), addBtn);
        //             }
        //             input.remove();
        //         }

        //         input.addEventListener('keydown', function(e) {
        //             if (e.key === 'Enter') {
        //                 e.preventDefault();
        //                 commitAmenity();
        //             } else if (e.key === 'Escape') {
        //                 input.remove();
        //             }
        //         });

        //         input.addEventListener('blur', commitAmenity);
        //     });
        // });
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