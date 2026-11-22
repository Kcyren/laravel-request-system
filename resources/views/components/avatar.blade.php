@props(['name', 'size' => null])
@php
    $initials = \Illuminate\Support\Str::of($name)
        ->replaceMatches('/\(.*?\)/', '')
        ->replaceMatches('/[^\pL ]/u', '')
        ->explode(' ')
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp
<span {{ $attributes->merge(['class' => 'avatar'.($size ? ' '.$size : '')]) }} aria-hidden="true">{{ $initials ?: '?' }}</span>
