<!DOCTYPE html>
<html class="scroll-smooth" lang="en" data-theme="mytheme">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>{{ $title ?? 'SunnyTrips | Travel Made Easy' }}</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <!-- Vite for Tailwind/Local CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Flatpickr Date Picker CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Custom inline styles for material icons -->
    <style>
        [x-cloak] {
            display: none !important;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .glass-nav {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .nav-active {
            background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 14px rgba(14, 165, 233, 0.35) !important;
            border: 1px solid rgba(56, 189, 248, 0.5) !important;
        }
        .signature-gradient {
            background: linear-gradient(135deg, #005f99 0%, #5eb1fc 100%);
        }
        .soft-lift {
            box-shadow: 0 8px 24px rgba(44, 47, 48, 0.04);
        }
        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.5, 0, 0, 1), transform 0.8s cubic-bezier(0.5, 0, 0, 1);
        }
        .reveal-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface antialiased">
    @unless($hideNavFooter ?? false)
        @auth
            @include('layouts.user-sidebar')
        @else
            <x-frontend.navbar />
        @endauth
    @endunless
    
    <main class="{{ (!($hideNavFooter ?? false) && Auth::check()) ? 'md:ms-64 transition-all duration-300' : '' }}">
        {{ $slot }}
    </main>

    @unless(($hideNavFooter ?? false) || Auth::check())
        <x-frontend.footer />
        <x-frontend.mobile-nav />
    @endunless

    <!-- Scroll Reveal Animation Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                root: null,
                rootMargin: "0px 0px -50px 0px",
                threshold: 0.1
            });

            document.querySelectorAll('.reveal-on-scroll').forEach((el) => {
                observer.observe(el);
            });
        });

        window.addToCart = async function(itemType, itemId, options = {}) {
            try {
                const bodyData = {
                    item_type: itemType,
                    item_id: Number(itemId),
                    quantity: options.quantity || 1,
                    selected_pax: options.selected_pax || 1,
                    check_in_date: options.check_in_date || null,
                    check_out_date: options.check_out_date || null,
                    notes: options.notes || null,
                };

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

                const res = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(bodyData)
                });

                const data = await res.json();
                if (data.success) {
                    window.dispatchEvent(new CustomEvent('cart-updated'));
                    window.dispatchEvent(new CustomEvent('show-cart-modal', {
                        detail: {
                            itemData: data.cart_item,
                            alreadyInCart: data.already_in_cart || false,
                            message: data.already_in_cart ? data.message : null
                        }
                    }));
                } else {
                    alert(data.message || 'Could not add item to basket.');
                }
            } catch (err) {
                console.error('Error adding to cart:', err);
                alert('Could not add item to your basket. Please try again.');
            }
        };
    </script>
    
    <x-frontend.cart-drawer />
    <x-frontend.cart-success-modal />
    <x-frontend.chat-widget />
</body>
</html>
