@props(['titel', 'ueber' => null, 'zurueck' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between animate-einblenden">
    <div class="min-w-0">
        @if ($zurueck)
            <a href="{{ $zurueck }}" class="label inline-flex items-center gap-1 hover:text-rot mb-2"><x-icon name="zurueck" class="w-3.5 h-3.5" /> Zurück</a>
        @endif
        @if ($ueber)<p class="label text-rot mb-1">{{ $ueber }}</p>@endif
        <h1 class="titel text-2xl sm:text-3xl break-words">{{ $titel }}</h1>
        @isset($unter)<div class="mt-2 text-sm text-beton">{{ $unter }}</div>@endisset
    </div>
    @isset($aktionen)
        <div class="flex flex-wrap gap-2 kein-druck">{{ $aktionen }}</div>
    @endisset
</div>
