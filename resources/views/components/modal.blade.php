@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'align' => 'center',
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];

// Right-aligned panels (e.g. over the auth form column) on desktop; still centered below lg.
$alignClass = $align === 'right' ? 'lg:mr-0' : '';
@endphp

{{-- Hidden Alpine root: x-teleport templates are only processed when Alpine walks into them,
     and template content is invisible to Alpine's [x-data] root query — so we wrap the
     teleport template in a minimal initialized root, then give the teleported panel its
     own scope (correct $el / focus trap). --}}
<div x-data="{}" class="hidden" aria-hidden="true">
    <template x-teleport="body">
        <div
            x-data="{
                show: @js($show),
                focusables() {
                    // All focusable element types...
                    let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
                    return [...$el.querySelectorAll(selector)]
                        // All non-disabled elements...
                        .filter(el => ! el.hasAttribute('disabled'))
                },
                firstFocusable() { return this.focusables()[0] },
                lastFocusable() { return this.focusables().slice(-1)[0] },
                nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
                prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
                nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
                prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
            }"
            x-init="$watch('show', value => {
                if (value) {
                    document.body.classList.add('overflow-y-hidden');
                    {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
                } else {
                    document.body.classList.remove('overflow-y-hidden');
                }
            })"
            x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
            x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
            x-on:close.stop="show = false"
            x-on:keydown.escape.window="show = false"
            x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
            x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
            x-show="show"
            class="fixed inset-0 z-50 flex overflow-y-auto px-4 py-6 sm:px-6 lg:px-16"
        >
            <div
                x-show="show"
                class="fixed inset-0 transform transition-all"
                x-on:click="show = false"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                <div class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"></div>
            </div>

            <div
                x-show="show"
                class="m-auto w-full {{ $alignClass }} bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all {{ $maxWidth }}"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                {{ $slot }}
            </div>
        </div>
    </template>
</div>