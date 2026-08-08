@props([
    'state' => 'working',
    'size' => 40,
    'speed' => 1,
    'paused' => false,
    'light' => false,
    'label' => null,
])

<canvas
    {{ $attributes->merge([
        'x-data' => 'thinkingOrb({ state: \'' . $state . '\', size: ' . (int) $size . ', speed: ' . (float) $speed . ', paused: ' . ($paused ? 'true' : 'false') . ', light: ' . ($light ? 'true' : 'false') . ' })',
        'role' => 'img',
        'aria-label' => $label ?? \Illuminate\Support\Str::headline($state),
        'style' => 'width: ' . (int) $size . 'px; height: ' . (int) $size . 'px; display: block;',
    ]) }}
></canvas>
