import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Primary sans-serif for body copy, UI, and labels
                sans:     ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                body:     ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                label:    ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                // Geometric display font for headlines and hero text
                display:  ['Sora', ...defaultTheme.fontFamily.sans],
                headline: ['Sora', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                // Philippine-ocean inspired blues — no purple anywhere
                ocean: {
                    50:  '#f0f9ff',
                    100: '#dff1fb',
                    200: '#b8e3f6',
                    300: '#79cbed',
                    400: '#38b0e3',
                    500: '#1294c8',
                    600: '#0a78a8',
                    700: '#085e85',
                    800: '#074e6d',
                    900: '#063f58',
                },
                // Warm sand tones for backgrounds & borders
                sand: {
                    50:  '#fdfaf4',
                    100: '#f8f1e1',
                    200: '#ede3cb',
                    300: '#ddd0ae',
                },
                // Neutral ink tones for text
                ink: {
                    100: '#f3f4f6',
                    200: '#e5e7eb',
                    300: '#d1d5db',
                    400: '#9ca3af',
                    500: '#6b7280',
                    600: '#4b5563',
                    700: '#374151',
                    900: '#111827',
                },
                // Warm coral accent (optional highlights)
                coral: {
                    400: '#f07250',
                    500: '#e85e37',
                    600: '#d24f29',
                },
            },

            keyframes: {
                'fade-up': {
                    '0%':   { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    '0%':   { opacity: '0' },
                    '100%': { opacity: '1' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.35s ease-out both',
                'fade-in': 'fade-in 0.25s ease-out both',
            },
        },
    },

    plugins: [forms],
};
