<!DOCTYPE html>
<html class="scroll-smooth" lang="en" data-theme="mytheme">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>{{ $title ?? 'SunnyTrips | Travel Made Easy' }}</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <!-- Vite for Tailwind/Local CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Custom inline styles for material icons -->
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .glass-nav {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
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
    <x-frontend.navbar />
    
    <main>
        {{ $slot }}
    </main>

    <x-frontend.footer />
    <x-frontend.mobile-nav />

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
    </script>
</body>
</html>
