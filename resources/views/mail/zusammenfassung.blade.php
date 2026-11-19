<x-mail::message>
# {{ $art === 'woche' ? 'Deine Woche im OK' : 'Dein Tag im OK' }}

Hallo {{ strtok($user->name, ' ') }}

@if ($meldungen->count())
**Neu für dich**

@foreach ($meldungen as $m)
- {{ $m->data['titel'] ?? '' }}: {{ $m->data['text'] ?? '' }}
@endforeach
@endif

@if ($aufgaben->count())
**{{ $art === 'woche' ? 'In den nächsten zwei Wochen fällig' : 'Bald fällig' }}**

<x-mail::table>
| Aufgabe | Ressort | Fällig |
|:--------|:--------|:-------|
@foreach ($aufgaben as $a)
| {{ $a->titel }} | {{ $a->ressort->kuerzel }} | {{ $a->faellig_am->format('d.m.') }}{{ $a->istUeberfaellig() ? ' (überfällig)' : '' }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="route('start')">
OK-Tool öffnen
</x-mail::button>

Sportliche Grüsse<br>
Dein OK
</x-mail::message>
