<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Einstellung;
use App\Models\Ressort;
use App\Models\Turnier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

/**
 * Claude-Assistent: macht aus Notizen, Listen, PDF oder Fotos Vorschlaege fuer Aufgaben.
 * Es wird nichts gespeichert; die Vorschlaege prueft und uebernimmt die Person selbst.
 */
class AufgabenAssistent
{
    public const MODELL = 'claude-opus-5-5';

    public const DATEITYPEN = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** API-Schluessel: zuerst aus Admin > System (verschluesselt), sonst aus .env */
    public function schluessel(): ?string
    {
        $gespeichert = Einstellung::wertSicher('claude_schluessel');
        if (filled($gespeichert)) {
            try {
                return Crypt::decryptString($gespeichert);
            } catch (\Throwable) {
                // z. B. aus einer Sicherung mit anderem APP_KEY: dann .env versuchen
            }
        }

        return filled(config('services.anthropic.key')) ? config('services.anthropic.key') : null;
    }

    public function bereit(): bool
    {
        return $this->schluessel() !== null;
    }

    /**
     * @param  UploadedFile[]  $dateien
     * @param  array{art: string, ressort?: ?Ressort, turnier?: ?string, ressorts: Collection, turniere: Collection}  $kontext
     * @return array{aufgaben: array<int, array>, hinweis: ?string}
     */
    public function vorschlaege(string $text, array $dateien, array $kontext): array
    {
        $inhalt = [];
        foreach ($dateien as $datei) {
            $mime = $datei->getMimeType();
            $daten = base64_encode((string) file_get_contents($datei->getRealPath()));
            $inhalt[] = $mime === 'application/pdf'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $daten]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => $daten]];
        }
        $inhalt[] = ['type' => 'text', 'text' => trim($text) !== '' ? $text : 'Bitte die Aufgaben aus den angehaengten Dateien erfassen.'];

        $antwort = $this->frage($this->systemPrompt($kontext), $inhalt);

        return $this->aufbereiten($antwort, $kontext);
    }

    /** Ruft Claude auf und gibt das JSON der Antwort zurueck (in Tests ueberschreibbar). */
    protected function frage(string $system, array $inhalt): array
    {
        $client = new Client(apiKey: $this->schluessel(), requestOptions: ['timeout' => 150, 'maxRetries' => 1]);
        $nachricht = $client->beta->messages->create(
            model: self::MODELL,
            maxTokens: 16000,
            system: $system,
            messages: [['role' => 'user', 'content' => $inhalt]],
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($nachricht->stopReason === 'refusal') {
            throw new \RuntimeException('Claude hat die Anfrage abgelehnt. Bitte den Text anders formulieren.');
        }
        if ($nachricht->stopReason === 'max_tokens') {
            throw new \RuntimeException('Die Liste ist zu lang für einen Durchgang. Bitte in kleineren Teilen einfügen.');
        }
        foreach ($nachricht->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                if (is_array($json)) {
                    return $json;
                }
            }
        }

        throw new \RuntimeException('Claude hat keine verwertbare Antwort geliefert. Bitte nochmals versuchen.');
    }

    protected function systemPrompt(array $kontext): string
    {
        $ressorts = $kontext['ressorts']->map(fn ($r) => '- '.$r->name.' (Kürzel '.$r->kuerzel.')'.($r->auftrag ? ': '.str($r->auftrag)->limit(160) : ''))->implode("\n");
        $turniere = $kontext['turniere']->map(fn ($t) => '- '.$t->name.($t->kurz ? ' ('.$t->kurz.')' : ''))->implode("\n");
        $ziel = $kontext['art'] === 'jahresplan'
            ? 'Die Aufgaben kommen in den Jahresplan, also in die Liste der Aufgaben, die bei jedem Turnier wiederkommen.'
            : 'Die Aufgaben kommen ins Turnier «'.($kontext['turnier'] ?? 'nächstes Turnier').'» beim Ressort «'.($kontext['ressort']?->name ?? '').'».';

        return <<<TXT
        Du hilfst dem Organisationskomitee (OK) des Raiffeisen-Hallencups des FC Münsterlingen, Aufgaben in ihr OK-Tool zu übertragen.
        Aus dem Text und den Dateien der Person machst du einzelne, klar formulierte Aufgaben. {$ziel}

        Ressorts im Tool:
        {$ressorts}

        Turnierarten im Tool:
        {$turniere}

        So füllst du die Felder:
        - titel: kurz und handlungsorientiert, z. B. «Halle reservieren». Sprache Deutsch (Schweiz), ss statt scharfem s.
        - beschreibung: nur was im Text steht (wer, wo, worauf achten), sonst null. Nichts erfinden.
        - ressort: Name eines Ressorts aus der Liste, das am besten passt, sonst null.
        - wann: «vor» (vor Turnierbeginn), «erster» (am ersten Turniertag), «letzter» (am letzten Turniertag), «nach» (nach Turnierende) oder «ohne», wenn kein Zeitpunkt erkennbar ist.
        - abstand_tage: bei «vor» und «nach» die Anzahl Tage (1 Woche = 7, 1 Monat = 30), sonst null. Ein konkretes Datum rechnest du nur um, wenn das Turnierdatum bekannt ist; sonst «ohne».
        - aufwand_minuten: nur wenn ein Aufwand genannt ist, sonst null.
        - prioritaet: «hoch» nur bei deutlich Wichtigem oder Fristen, sonst «normal».
        - jedes_jahr: true, wenn die Aufgabe offensichtlich jedes Jahr wiederkommt, false bei Einmaligem.
        - pro_saison: true, wenn die Aufgabe nur einmal pro Saison für alle Turniere zusammen anfällt (z. B. Sponsoren anfragen, Saisonbudget, Plakat mit allen Daten), sonst false.
        - turnierarten: Namen der Turnierarten, für die die Aufgabe gilt; leere Liste, wenn sie für alle gilt oder pro_saison true ist.
        - checkliste: Unterpunkte, wenn der Text welche nennt, sonst leere Liste.
        Doppelte Aufgaben fasst du zusammen. Unter hinweis notierst du kurz, was unklar war, sonst null.
        TXT;
    }

    public static function schema(): array
    {
        $oderNull = fn (array $typ) => ['anyOf' => [$typ, ['type' => 'null']]];

        return [
            'type' => 'object',
            'properties' => [
                'aufgaben' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'titel' => ['type' => 'string'],
                            'beschreibung' => $oderNull(['type' => 'string']),
                            'ressort' => $oderNull(['type' => 'string']),
                            'wann' => ['type' => 'string', 'enum' => ['vor', 'erster', 'letzter', 'nach', 'ohne']],
                            'abstand_tage' => $oderNull(['type' => 'integer']),
                            'aufwand_minuten' => $oderNull(['type' => 'integer']),
                            'prioritaet' => ['type' => 'string', 'enum' => ['tief', 'normal', 'hoch']],
                            'jedes_jahr' => ['type' => 'boolean'],
                            'pro_saison' => ['type' => 'boolean'],
                            'turnierarten' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'checkliste' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['titel', 'beschreibung', 'ressort', 'wann', 'abstand_tage', 'aufwand_minuten', 'prioritaet', 'jedes_jahr', 'pro_saison', 'turnierarten', 'checkliste'],
                        'additionalProperties' => false,
                    ],
                ],
                'hinweis' => $oderNull(['type' => 'string']),
            ],
            'required' => ['aufgaben', 'hinweis'],
            'additionalProperties' => false,
        ];
    }

    /** Antwort in Formularwerte des Tools uebersetzen (Ressort-ID, Abstand, Turnierart-IDs). */
    protected function aufbereiten(array $antwort, array $kontext): array
    {
        $finde = function (?string $name, Collection $liste, array $felder) {
            if (blank($name)) {
                return null;
            }
            $n = mb_strtolower(trim($name));

            return $liste->first(fn ($e) => collect($felder)->contains(fn ($f) => filled($e->{$f}) && mb_strtolower($e->{$f}) === $n))
                ?? $liste->first(fn ($e) => str_contains(mb_strtolower($e->name), $n) || str_contains($n, mb_strtolower($e->name)));
        };

        $aufgaben = collect($antwort['aufgaben'] ?? [])->filter(fn ($a) => filled($a['titel'] ?? null))->map(function ($a) use ($kontext, $finde) {
            $ressort = $kontext['art'] === 'ressort' && ! empty($kontext['ressort']) ? $kontext['ressort'] : $finde($a['ressort'] ?? null, $kontext['ressorts'], ['name', 'kuerzel']);
            $abstand = max(0, (int) ($a['abstand_tage'] ?? 0));
            [$tage, $bezug] = match ($a['wann'] ?? 'ohne') {
                'vor' => [$abstand, 'beginn'],
                'erster' => [0, 'beginn'],
                'letzter' => [0, 'ende'],
                'nach' => [-$abstand, 'ende'],
                default => [null, 'beginn'],
            };
            $turniere = collect($a['turnierarten'] ?? [])->map(fn ($t) => $finde($t, $kontext['turniere'], ['name', 'kurz'])?->id)->filter()->unique()->values()->all();

            return [
                'titel' => mb_substr(trim($a['titel']), 0, 200),
                'beschreibung' => filled($a['beschreibung'] ?? null) ? trim($a['beschreibung']) : null,
                'ressort_id' => $ressort?->id,
                'tage_vorher' => $tage,
                'bezug' => $bezug,
                'aufwand' => filled($a['aufwand_minuten'] ?? null) ? round(max(0, (int) $a['aufwand_minuten']) / 60, 2) : null,
                'prioritaet' => in_array($a['prioritaet'] ?? null, ['tief', 'normal', 'hoch'], true) ? $a['prioritaet'] : 'normal',
                'jedes_jahr' => (bool) ($a['jedes_jahr'] ?? false),
                'pro_saison' => (bool) ($a['pro_saison'] ?? false),
                'turniere' => ($a['pro_saison'] ?? false) ? [] : $turniere,
                'checkliste' => collect($a['checkliste'] ?? [])->map(fn ($p) => trim((string) $p))->filter()->values()->all(),
            ];
        })->values()->all();

        return ['aufgaben' => $aufgaben, 'hinweis' => filled($antwort['hinweis'] ?? null) ? $antwort['hinweis'] : null];
    }
}
