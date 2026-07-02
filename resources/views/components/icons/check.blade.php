{{-- Checkmark (decorative), used on the success confirmation. Its container
     carries aria-hidden; size overridable via attributes. --}}
<svg {{ $attributes->merge([
    'width' => 46,
    'height' => 46,
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => 3,
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
    'aria-hidden' => 'true',
    'focusable' => 'false',
]) }}>
    <path d="M20 6 9 17l-5-5"/>
</svg>
