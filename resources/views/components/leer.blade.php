@props(['icon' => 'check', 'titel', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-10 px-6 text-center']) }}>
    <div class="flex h-12 w-12 items-center justify-center border-2 border-nacht/15 text-beton mb-3"><x-icon :name="$icon" class="w-6 h-6" /></div>
    <p class="font-bold uppercase tracking-label text-sm">{{ $titel }}</p>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-beton">{{ $text }}</p>@endif
    {{ $slot }}
</div>
