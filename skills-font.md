# SunnyTrips Typography & Font System Guide (`skills-font.md`)

This document serves as the authoritative reference for typography decisions, font pairings, Tailwind CSS configuration, and text styling conventions across the **SunnyTrips** travel booking system.

---

## 1. Core Font Pairing

SunnyTrips utilizes a modern two-font combination engineered to balance premium luxury travel aesthetics with clean readability.

| Role | Font Family | Style Category | Intended Use Cases |
| :--- | :--- | :--- | :--- |
| **Headline / Display** | **`Sora`** | Geometric Sans-Serif | Main Hero titles (`h1`), section titles (`h2`), card titles (`h3`, `h4`), brand wordmarks, and prominent display quotes. |
| **Body / UI / Label** | **`DM Sans`** | Geometric/Grotesque Sans-Serif | Body paragraphs (`p`), UI form inputs, buttons, navigation links, section eyebrows/badges, and meta text. |

### Why This Combination?
* **`Sora`** provides a bold, modern geometric feel with high-character personality that immediately communicates luxury and modern travel.
* **`DM Sans`** provides optimal legibility across desktop and mobile devices at small and medium text sizes, with warm humanistic curves that complement Sora seamlessly.

---

## 2. Google Fonts Loading Strategy

Fonts are loaded asynchronously via Google Fonts with `preconnect` optimization.

### CDN Snippet

```html
<!-- Google Fonts: Sora (Headlines) + DM Sans (Body & UI) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap" rel="stylesheet"/>
```

> **Note:** Ensure this CDN tag is present in both primary layout shells:
> 1. `resources/views/components/frontend/layout.blade.php` (Public Landing / Booking Pages)
> 2. `resources/views/layouts/guest.blade.php` (Auth pages: Login, Register, Password Reset)

---

## 3. Tailwind CSS Configuration

Typography utilities are centrally mapped in `tailwind.config.js` to ensure uniform usage and eliminate hardcoded inline font family declarations.

```javascript
// tailwind.config.js
import defaultTheme from 'tailwindcss/defaultTheme';

export default {
    theme: {
        extend: {
            fontFamily: {
                // Primary sans-serif for body copy, UI, and labels (DM Sans)
                sans:     ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                body:     ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                label:    ['"DM Sans"', ...defaultTheme.fontFamily.sans],

                // Display font for headlines and hero text (Sora)
                display:  ['Sora', ...defaultTheme.fontFamily.sans],
                headline: ['Sora', ...defaultTheme.fontFamily.sans],
            },
        },
    },
};
```

---

## 4. Typography Hierarchy & Utility Guide

| Element Level | Tailwind Class Mix | Recommended Usage |
| :--- | :--- | :--- |
| **Hero Title (`h1`)** | `font-headline text-5xl md:text-8xl font-extrabold tracking-tighter leading-tight` | Impactful top-of-page hero headers |
| **Section Title (`h2`)** | `font-headline text-3xl md:text-5xl font-extrabold tracking-tight leading-tight` | Primary section headers (`Destinations`, `Brand Story`, `Testimonials`) |
| **Auth Heading (`h1`)** | `font-headline text-[1.875rem] font-semibold text-ink-900 leading-tight` | Login & Registration page headers |
| **Card / Subheading (`h3`/`h4`)** | `font-headline text-lg font-bold text-slate-900` | Destination titles, feature card titles |
| **Section Eyebrow / Label** | `font-label text-xs uppercase font-bold tracking-[0.2em] text-primary` | Overline badges (`CURATED COLLECTIONS`, `OUR PHILOSOPHY`) |
| **Micro Label / Badge** | `font-label text-[10px] uppercase font-bold tracking-[0.15em]` | Taglines on card media badges (`PALAWAN`, `AKLAN`) |
| **Body Paragraph (`p`)** | `font-body text-xs md:text-sm text-slate-500 leading-relaxed` | Descriptive text, card details, brand copy |
| **Primary Buttons** | `font-body text-sm font-bold tracking-normal` | CTA buttons (`Login`, `Register`, `Submit`) |
| **Form Labels (`label`)** | `font-body text-xs font-semibold uppercase tracking-wider text-ink-600` | Input field labels (`EMAIL ADDRESS`, `PASSWORD`) |

---

## 5. Font Weight Standards

| Weight | Tailwind Class | Recommended Purpose |
| :--- | :--- | :--- |
| **300 (Light)** | `font-light` | Contrast emphasis inside headers (e.g. `<em class="font-light">Extraordinary</em>`) |
| **400 (Regular)** | `font-normal` | Standard body narrative copy and placeholders |
| **500 (Medium)** | `font-medium` | Navigation links, helper captions, secondary metadata |
| **600 (SemiBold)** | `font-semibold` | Form field labels, secondary CTAs, auth headings |
| **700 (Bold)** | `font-bold` | Card headings, section badges, main action buttons |
| **800 (ExtraBold)** | `font-extrabold` | Major section headers (`h1`, `h2`) |

---

## 6. Letter Spacing & Line Height Rules

1. **Large Headlines (`h1`, `h2`)**:
   - Use `tracking-tighter` or `tracking-tight` with `leading-tight`.
   - Large Sora characters look best tightly tracked to maintain visual density.

2. **Uppercase Eyebrows & Badges**:
   - Always apply `tracking-[0.2em]` or `tracking-[0.15em]` along with `uppercase` and `font-bold`.
   - Wide tracking improves scannability for small uppercase tags.

3. **Body Paragraphs**:
   - Always pair `text-xs md:text-sm` with `leading-relaxed` for maximum legibility across mobile and desktop displays.

---

## 7. Accessibility & Responsive Guidelines

* **Color Contrast**: Ensure text in `DM Sans` meets WCAG AA contrast against backgrounds (`text-slate-900` or `text-slate-600` on light surfaces, `text-white` on dark overlays).
* **Responsive Scaling**: Use Tailwind breakpoint prefixes (`text-3xl md:text-5xl`) so headings adapt smoothly from mobile screens (375px) up to ultra-wide displays.
* **Semantic HTML**: Always nest headings logically (`h1` -> `h2` -> `h3`) regardless of visual font styling classes applied.

---

## 8. Development Best Practices

1. **No Inline Hardcoded Font Families**: Never write `font-['Plus_Jakarta_Sans']` or inline CSS font rules in `.blade.php` files. Always use `font-headline`, `font-body`, or `font-label`.
2. **Layout Consistency**: Whenever creating a new layout file or modal shell, ensure Google Fonts imports for **Sora** and **DM Sans** are present in `<head>`.
3. **Component Reusability**: Use `<x-input-label>` and `<x-primary-button>` components to keep form controls automatically synced with standard font tokens.
