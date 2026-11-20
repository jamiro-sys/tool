@props(['name', 'titel', 'auf' => false])
<div x-data="{ offen: {{ $auf ? 'true' : 'false' }} }" x-on:modal-{{ $name }}.window="offen = true" x-on:keydown.escape.window="offen = false" x-cloak>
    <div x-show="offen" x-transition.opacity class="fixed inset-0 z-50 bg-nacht/60" x-on:click="offen = false"></div>
    <div x-show="offen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed inset-x-0 bottom-0 z-50 max-h-[92vh] overflow-y-auto bg-white sm:inset-auto sm:left-1/2 sm:top-[8vh] sm:w-full sm:max-w-lg sm:-translate-x-1/2" role="dialog" aria-modal="true">
        <div class="karte-kopf sticky top-0 bg-white z-10">
            <h2 class="titel text-base">{{ $titel }}</h2>
            <button type="button" class="knopf-leise knopf-klein" x-on:click="offen = false" aria-label="Schliessen"><x-icon name="x" class="w-4 h-4" /></button>
        </div>
        <div class="p-4">{{ $slot }}</div>
    </div>
</div>
