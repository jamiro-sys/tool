<x-mail::message>
# Willkommen im OK

Hallo {{ strtok($user->name, ' ') }}

Du wurdest ins OK-Tool des Raiffeisen-Hallencups eingeladen. Hier findest du deine Aufgaben, die Unterlagen deines Ressorts und alles, was das OK für die Turniere braucht.

<x-mail::button :url="$link" color="primary">
Zugang einrichten
</x-mail::button>

Der Link ist persönlich. Bitte nicht weiterleiten.

Sportliche Grüsse<br>
Dein OK
</x-mail::message>
