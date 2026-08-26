<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="icon" type="image/png" href="{{ asset('images/favicon-sun.png') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <x-frontend.cart-drawer />

        <script>
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
                            detail: { itemData: data.cart_item }
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
        <x-frontend.cart-success-modal />
    </body>
</html>
