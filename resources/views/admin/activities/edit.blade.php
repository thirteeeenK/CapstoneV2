@extends('layouts.admin')

@section('title', 'Edit Activity | SunnyTrips Admin')

@section('content')
    <div class="p-8 max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.activities.index') }}" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 font-semibold transition-colors mb-2">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Back to Activities
                </a>
                <h1 class="text-xl font-bold text-slate-900">Edit Activity: {{ $activity->activity_name }}</h1>
                <p class="text-xs text-slate-500 mt-1">Update activity details. Vector embeddings will automatically regenerate on save.</p>
            </div>
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

        <div class="bg-white border border-slate-200 rounded-lg shadow-sm p-6">
            <form action="{{ route('admin.activities.update', $activity->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

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
                                <option value="{{ $dest->id }}" {{ old('destination_id', $activity->destination_id) == $dest->id ? 'selected' : '' }}>
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
                        <input type="text" id="activity_name" name="activity_name" value="{{ old('activity_name', $activity->activity_name) }}" required
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
                            @foreach(['Island Hopping', 'Land Tour', 'Water Activity', 'Diving', 'Adventure', 'Sailing', 'Other'] as $cat)
                                <option value="{{ $cat }}" {{ old('category', $activity->category) == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
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
                            <option value="Relaxing" {{ old('activity_level', $activity->activity_level) == 'Relaxing' ? 'selected' : '' }}>Relaxing</option>
                            <option value="Sightseeing" {{ old('activity_level', $activity->activity_level) == 'Sightseeing' ? 'selected' : '' }}>Sightseeing</option>
                            <option value="Adventure" {{ old('activity_level', $activity->activity_level) == 'Adventure' ? 'selected' : '' }}>Adventure</option>
                            <option value="Extreme" {{ old('activity_level', $activity->activity_level) == 'Extreme' ? 'selected' : '' }}>Extreme</option>
                            <option value="Underwater" {{ old('activity_level', $activity->activity_level) == 'Underwater' ? 'selected' : '' }}>Underwater</option>
                        </select>
                    </div>

                    <!-- Rate -->
                    <div class="space-y-1.5">
                        <label for="rate" class="block text-xs font-semibold text-slate-700">
                            Rate / Pricing <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="rate" name="rate" value="{{ old('rate', $activity->rate) }}" required
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Duration -->
                    <div class="space-y-1.5">
                        <label for="duration" class="block text-xs font-semibold text-slate-700">
                            Duration
                        </label>
                        <input type="text" id="duration" name="duration" value="{{ old('duration', $activity->duration) }}"
                               placeholder="e.g. 3 Hours, Half Day, Full Day, 15 Mins"
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Capacity -->
                    <div class="space-y-1.5">
                        <label for="capacity" class="block text-xs font-semibold text-slate-700">
                            Group Capacity
                        </label>
                        <input type="text" id="capacity" name="capacity" value="{{ old('capacity', $activity->capacity) }}"
                               placeholder="e.g. 1-6 Pax, Max 10 Guests, Solo or Groups"
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Ideal For / Target Audience -->
                    <div class="space-y-1.5">
                        <label for="ideal_for" class="block text-xs font-semibold text-slate-700">
                            Target Audience / Ideal Participants
                        </label>
                        <input type="text" id="ideal_for" name="ideal_for" value="{{ old('ideal_for', $activity->ideal_for) }}"
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
                                  class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('requirements', $activity->requirements) }}</textarea>
                    </div>

                    <!-- Vibe Tags -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="vibe_tags" class="block text-xs font-semibold text-slate-700">
                            Experience Vibes / Tags (Comma-separated)
                        </label>
                        @php
                            $vibeStr = is_array($activity->vibe_tags) ? implode(', ', $activity->vibe_tags) : $activity->vibe_tags;
                        @endphp
                        <input type="text" id="vibe_tags" name="vibe_tags" value="{{ old('vibe_tags', $vibeStr) }}"
                               placeholder="e.g. Snorkeling, Island Hopping, Panoramic Views, Adrenaline"
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <!-- Detailed Description -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="description" class="block text-xs font-semibold text-slate-700">
                            Detailed Experience Description
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Describe the experience in detail..."
                                  class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('description', $activity->description) }}</textarea>
                    </div>

                    <!-- Notes / Inclusions -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="notes" class="block text-xs font-semibold text-slate-700">
                            Notes / Inclusions
                        </label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="w-full bg-slate-50 border border-slate-300 rounded-md p-3 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('notes', $activity->notes) }}</textarea>
                    </div>

                    <!-- Image Gallery Section (Adapted from edit_hotel.blade.php) -->
                    <div class="space-y-3 md:col-span-2 pt-2 border-t border-slate-100">
                        {{-- Container for removed image inputs --}}
                        <div id="removedImagesContainer"></div>

                        {{-- Hidden file input --}}
                        <input type="file" id="activityImageUpload" name="images[]" multiple accept="image/*" class="hidden"
                            onchange="handleImagePreview(this)" />

                        <div class="flex justify-between items-center">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                                Photo Gallery
                            </label>
                            <button type="button" onclick="document.getElementById('activityImageUpload').click()"
                                class="inline-flex items-center gap-2 h-8 px-3 rounded-md bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 text-xs font-medium transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">cloud_upload</span>
                                Upload Images
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-3 max-w-md" id="imagePreviewContainer">
                            {{-- Boxes 1-3 (for Previews - HTML Skeleton only, JS will fill this) --}}
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
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <a href="{{ route('admin.activities.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-md transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-5 py-2 bg-ocean-600 hover:bg-ocean-700 text-white text-xs font-semibold rounded-md shadow-sm transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">sync</span>
                        Update & Regenerate Embedding
                    </button>
                </div>
            </form>
        </div>
    </div>

    @php
        $rawImages = $activity->images;
        if (!is_array($rawImages)) {
            $rawImages = json_decode($rawImages, true) ?? [];
        }
        $existingImagesData = [];
        if (is_array($rawImages)) {
            foreach ($rawImages as $img) {
                $existingImagesData[] = [
                    'path' => $img,
                    'url' => asset('storage/' . $img),
                ];
            }
        }
    @endphp

    {{-- Image Management Script (Adapted from edit_hotel.blade.php) --}}
    <script>
        let existingImages = @json($existingImagesData);
        let removedExistingPaths = [];
        let imageBasket = new DataTransfer();

        const fileInput = document.getElementById('activityImageUpload');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const previewSlots = previewContainer.querySelectorAll('.preview-slot');
        const removedImagesContainer = document.getElementById('removedImagesContainer');

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
            if (fileInput) {
                fileInput.files = imageBasket.files;
            }
        }

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

        function removeExistingImage(imagePath) {
            removedExistingPaths.push(imagePath);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_images[]';
            input.value = imagePath;
            removedImagesContainer.appendChild(input);

            renderGallery();
        }

        function renderGallery() {
            let displayItems = [];

            existingImages.forEach(img => {
                if (!removedExistingPaths.includes(img.path)) {
                    displayItems.push({
                        type: 'existing',
                        data: img
                    });
                }
            });

            Array.from(imageBasket.files).forEach(file => {
                displayItems.push({
                    type: 'new',
                    data: file
                });
            });

            previewSlots.forEach((slot, index) => {
                const defaultContent = slot.querySelector('.default-content');
                const previewContent = slot.querySelector('.image-preview-content');
                const removeBtn = slot.querySelector('.remove-image-btn');

                if (index < displayItems.length) {
                    const item = displayItems[index];
                    defaultContent.classList.add('hidden');
                    previewContent.classList.remove('hidden');

                    if (item.type === 'existing') {
                        previewContent.style.backgroundImage = `url('${item.data.url}')`;
                        removeBtn.onclick = (event) => {
                            event.stopPropagation();
                            removeExistingImage(item.data.path);
                        };
                    } else if (item.type === 'new') {
                        let reader = new FileReader();
                        reader.onload = function (e) {
                            previewContent.style.backgroundImage = `url('${e.target.result}')`;
                        };
                        reader.readAsDataURL(item.data);
                        removeBtn.onclick = (event) => {
                            event.stopPropagation();
                            removeNewImage(item.data);
                        };
                    }
                } else {
                    defaultContent.classList.remove('hidden');
                    previewContent.classList.add('hidden');
                    previewContent.style.backgroundImage = '';
                    removeBtn.onclick = null;
                }
            });
        }
    </script>
@endsection
