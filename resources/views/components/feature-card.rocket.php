@props(['title', 'icon' => '🚀'])

<div {{ $attributes->merge(['class' => 'card']) }}>
    <h3>{{ $icon }} {{ $title }}</h3>
    <p>{{ $slot }}</p>
</div>

