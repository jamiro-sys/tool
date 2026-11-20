@props(['turniere', 'gewaehlt' => [], 'klein' => false])
{{-- Haekchen je Turnierart (Mehrfachauswahl). Keine Auswahl gespeichert heisst: alle sind angehakt. --}}
@php $gewaehlt = collect($gewaehlt)->map(fn ($i) => (int) $i); $alle = $gewaehlt->isEmpty(); @endphp
@if ($turniere->isNotEmpty())
    <input type="hidden" name="turniere_gesendet" value="1">
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }} role="group" aria-label="Gilt für Turnierart">
        @foreach ($turniere as $t)
            <label class="flex cursor-pointer items-center gap-2 border border-nacht/20 bg-white {{ $klein ? 'min-h-[44px] px-3 text-sm' : 'px-3 py-2.5' }} has-[:checked]:border-rot has-[:checked]:bg-rot/5">
                <input type="checkbox" name="turniere[]" value="{{ $t->id }}" @checked($alle || $gewaehlt->contains($t->id)) class="h-4 w-4">
                <span class="{{ $klein ? '' : 'text-sm font-bold' }}">{{ $t->name }}</span>
            </label>
        @endforeach
    </div>
@endif
