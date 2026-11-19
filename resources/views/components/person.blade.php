@props(['user' => null, 'gross' => false])
@if ($user)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 min-w-0']) }} title="{{ $user->name }}">
        <span class="{{ $gross ? 'h-9 w-9 text-xs' : 'h-6 w-6 text-[10px]' }} inline-flex shrink-0 items-center justify-center bg-nacht font-bold text-white">{{ $user->initialen() }}</span>
        {{ $slot->isEmpty() ? '' : $slot }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-beton']) }}>
        <span class="{{ $gross ? 'h-9 w-9' : 'h-6 w-6' }} inline-flex shrink-0 items-center justify-center border border-dashed border-beton/50 text-[10px]">?</span>
        {{ $slot->isEmpty() ? '' : $slot }}
    </span>
@endif
