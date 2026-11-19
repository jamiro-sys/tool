@php $farbe = ['gut' => 'bg-rot', 'mittel' => 'bg-nacht', 'tief' => 'bg-gelb'][$stand['stufe']]; @endphp
<div {{ $attributes ?? '' }}>
    <div class="flex items-center justify-between text-xs"><span class="label">Übergabe-Bereitschaft</span><span class="zahl text-sm">{{ $stand['punkte'] }}%</span></div>
    <div class="balken mt-1.5 h-1.5" x-data="balken({{ $stand['punkte'] }})"><span class="{{ $farbe }}" :style="`width: ${breite}%`"></span></div>
</div>
