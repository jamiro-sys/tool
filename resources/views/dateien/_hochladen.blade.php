{{-- Hochladen in Ressort und Unterordner. Erwartet $ressorts, $ordner; optional $vorRessort (fixes Ressort, z. B. im Steckbrief) --}}
@php
    $fix = $vorRessort ?? null;
    $start = (string) ($fix ?? request('ressort', ''));
    $start = $start === 'allgemein' ? '' : $start;
@endphp
<form method="POST" action="{{ route('dateien.speichern') }}" enctype="multipart/form-data" class="karte mb-6 p-4"
      x-data="dateiAblage(@js($ressorts->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'kuerzel' => $r->kuerzel])->values()), @js($start), @js($ordner->map(fn ($o) => ['id' => $o->id, 'ressort_id' => $o->ressort_id, 'name' => $o->name])->values()), {{ $fix ? 'true' : 'false' }})">@csrf
    <div class="mb-3 grid grid-cols-2 gap-1 text-[11px] font-bold uppercase tracking-label sm:w-72">
        <button type="button" class="py-2" :class="art === 'datei' ? 'bg-nacht text-white' : 'border border-nacht/20'" x-on:click="art = 'datei'">Dateien</button>
        <button type="button" class="py-2" :class="art === 'link' ? 'bg-nacht text-white' : 'border border-nacht/20'" x-on:click="art = 'link'">Link</button>
    </div>
    <label x-show="art === 'datei'" class="flex min-h-[132px] cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed px-4 py-6 text-center transition-colors"
           :class="ueber ? 'border-rot bg-rot/5' : 'border-nacht/20 hover:border-nacht/50'"
           x-on:dragover.prevent="ueber = true" x-on:dragleave.prevent="ueber = false" x-on:drop.prevent="ablegen($event)">
        <x-icon name="upload" class="h-7 w-7 text-beton" />
        <span class="text-sm font-bold" x-text="namen.length ? namen.length + (namen.length === 1 ? ' Datei gewählt' : ' Dateien gewählt') : 'Dateien hierher ziehen oder antippen'"></span>
        <span class="max-w-full truncate text-xs text-beton" x-text="namen.length > 1 ? 'Titel: ' + titelListe.join(', ') : (namen.length ? namen[0] : 'Bis 50 MB pro Datei, mehrere auf einmal möglich')"></span>
        <input type="file" name="dateien[]" multiple class="sr-only" x-ref="eingabe" x-on:change="gewaehlt()">
    </label>
    <input x-show="art === 'link'" x-cloak name="url" type="url" class="feld" placeholder="https:// z. B. iCloud, Google Drive, OneDrive" aria-label="Link">
    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-start">
        @if ($fix)
            <input type="hidden" name="ressort_id" value="{{ $fix }}">
        @else
            <div><label class="feld-label" for="h-ressort">Ressort</label>
                <select id="h-ressort" name="ressort_id" class="feld" x-model="ressort" x-on:change="ressortVonHand = true; vorgeschlagen = false; ordnerWahl = ''"><option value="">Allgemein (kein Ressort)</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
                <p x-show="vorgeschlagen && art === 'datei'" x-cloak class="mt-1 text-xs text-beton">Aus dem Dateinamen vorgeschlagen, bei Bedarf ändern.</p></div>
        @endif
        <div><label class="feld-label" for="h-ordner">Ordner</label>
            <select id="h-ordner" name="ordner_id" class="feld" x-model="ordnerWahl">
                <option value="">Kein Unterordner</option>
                <template x-for="o in ordnerListe" :key="o.id"><option :value="String(o.id)" x-text="o.name"></option></template>
                <option value="neu">Neuer Ordner ...</option>
            </select>
            <input x-show="ordnerWahl === 'neu'" x-cloak name="neuer_ordner" maxlength="120" class="feld mt-2" placeholder="Name des neuen Ordners" :required="ordnerWahl === 'neu'" aria-label="Name des neuen Ordners"></div>
        <div><label class="feld-label" for="h-titel">Titel</label>
            <input id="h-titel" name="titel" maxlength="200" class="feld" x-model="titel" x-on:input="titelVonHand = titel !== ''" :disabled="art === 'datei' && namen.length > 1" :placeholder="art === 'link' ? 'Titel des Links' : (namen.length > 1 ? 'je Datei aus dem Dateinamen' : 'wird aus dem Dateinamen vorgeschlagen')"></div>
        <div class="lg:pt-[22px]"><button class="knopf-rot w-full" :disabled="art === 'datei' && ! namen.length"><x-icon name="upload" class="h-4 w-4" /> Ablegen</button></div>
    </div>
</form>
