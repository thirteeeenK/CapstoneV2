---
name: typography-system
description: Standard typography guidelines, font pairing (Sora + DM Sans), Tailwind configuration, and sizing rules for SunnyTrips.
---

# Typography & Font System

This skill outlines the typography conventions, font families, Tailwind setup, and responsive styling rules for SunnyTrips.

## Font Pairing Rules

- **Headline & Display Font (`Sora`)**: Used for `h1`, `h2`, `h3`, `h4`, quotes, and brand wordmarks via `font-headline` or `font-display`.
- **Body & UI Font (`DM Sans`)**: Used for body paragraphs, buttons, navigation links, form inputs, and eyebrows/labels via `font-body`, `font-label`, or `font-sans`.

## Tailwind Configuration Mapping

```javascript
fontFamily: {
    sans:     ['"DM Sans"', ...defaultTheme.fontFamily.sans],
    body:     ['"DM Sans"', ...defaultTheme.fontFamily.sans],
    label:    ['"DM Sans"', ...defaultTheme.fontFamily.sans],
    display:  ['Sora', ...defaultTheme.fontFamily.sans],
    headline: ['Sora', ...defaultTheme.fontFamily.sans],
}
```

## CDN Import Requirements

All Blade layouts (`<x-frontend.layout>`, `<x-guest-layout>`) must include:

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
    href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap"
    rel="stylesheet"
/>
```

## Guidelines & Best Practices

1. **No Inline Fonts**: Never use hardcoded inline font family classes like `font-['Font_Name']`. Always use `font-headline` or `font-body`.
2. **Section Eyebrows**: Use `font-label text-xs uppercase font-bold tracking-[0.2em] text-primary` for section category tags.
3. **Paragraphs**: Always use `font-body text-xs md:text-sm text-slate-500 leading-relaxed`.
