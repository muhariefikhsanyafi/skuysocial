@props(['name', 'weight' => 'regular'])

@php
    $weight = in_array($weight, ['regular', 'solid', 'brands'], true) ? $weight : 'regular';
@endphp

<i {{ $attributes->class(['fa', 'fa-'.$weight, 'fa-'.$name, 'skuy-fa-icon']) }} aria-hidden="true"></i>
