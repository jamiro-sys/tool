@props(['status'])
<span class="marke-{{ $status }}">{{ \App\Models\Aufgabe::STATUS[$status] ?? $status }}</span>
