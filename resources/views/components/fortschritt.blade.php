@props(['prozent' => 0, 'hoehe' => 'h-1.5'])
<div x-data="balken({{ (int) $prozent }})" {{ $attributes->merge(['class' => "balken $hoehe"]) }} role="progressbar" aria-valuenow="{{ (int) $prozent }}" aria-valuemin="0" aria-valuemax="100">
    <span :style="`width: ${breite}%`" style="width: {{ (int) $prozent }}%"></span>
</div>
