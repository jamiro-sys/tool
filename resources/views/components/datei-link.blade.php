@props(['d'])
{{-- Link auf eine Datei: PDF und Bilder oeffnen in der Vorschau im Tool, alles andere wie gewohnt --}}
@php $v = $d->vorschau(); @endphp
<a href="{{ $v ? $v['url'] : $d->link() }}" @if ($v) x-on:click.prevent="$store.vorschau.oeffne(@js($v))" @else target="_blank" rel="noopener" @endif {{ $attributes }}>{{ $slot }}</a>
