{{-- Hinweis, wenn fuer ein Formular noch kein Ressort existiert --}}
<div {{ $attributes->merge(['class' => 'border-l-4 border-gelb bg-kreide p-4 text-sm']) }}>
    <p class="font-bold">Zuerst ein Ressort anlegen</p>
    <p class="mt-1 text-beton">Aufgaben und Vorlagen gehören immer zu einem Ressort, z. B. Festwirtschaft oder Spielbetrieb.</p>
    @can('admin')<a href="{{ route('ressorts.index') }}" class="knopf-nacht knopf-klein mt-3">Zu den Ressorts</a>@else<p class="mt-2 text-beton">Ein Admin kann Ressorts anlegen.</p>@endcan
</div>
