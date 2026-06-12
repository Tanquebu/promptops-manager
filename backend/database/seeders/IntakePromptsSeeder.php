<?php

namespace Database\Seeders;

use App\Models\Prompt;
use App\Models\PromptEnvironment;
use App\Models\PromptVersion;
use Illuminate\Database\Seeder;

class IntakePromptsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedClassifyContent();
        $this->seedWeeklyBriefing();
        $this->seedResearchQuickbrief();
        $this->seedResearchDeepdive();
        $this->seedJobFilter();
    }

    private function seedPrompt(string $slug, string $name, string $description, array $variables, string $content): void
    {
        $prompt = Prompt::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => $description, 'variables' => $variables]
        );

        $version = PromptVersion::firstOrCreate(
            ['prompt_id' => $prompt->id, 'version_number' => 1],
            ['content' => $content, 'status' => 'published']
        );

        PromptEnvironment::updateOrCreate(
            ['prompt_id' => $prompt->id, 'environment' => 'production'],
            ['prompt_version_id' => $version->id, 'promoted_at' => now(), 'promoted_by' => 'admin@promptops.test']
        );
    }

    private function seedClassifyContent(): void
    {
        $content = <<<'PROMPT'
Sei un assistente che classifica contenuti per un professionista IT in transizione verso ruoli di IT Manager / Tech Lead con focus AI.

Ricevi:
- URL: {{url}}
- Contenuto estratto: {{content}}
- Tag inviati dall'utente: {{tags}}

Rispondi ESCLUSIVAMENTE con un oggetto JSON valido, nessun testo fuori dal JSON.

{
  "title": "titolo pulito, max 80 caratteri",
  "summary": "sommario in 2-3 frasi in italiano",
  "keywords": ["max 5 keyword"],
  "category": "uno tra: news|linkedin-post|linkedin-article|tool|research|job-opportunity|community",
  "suggested_workflow_tags": ["uno o più tra: post-linkedin|commento-linkedin|articolo-sito|linkografia-ai|formazione|profilo-interessante|backlog"],
  "rating": <intero da 1 a 5 secondo i criteri>,
  "rating_reason": "una riga di motivazione del rating"
}

Criteri rating generali (1-5) — 3 è il default per contenuto tech rilevante ma non specifico:
5 = Actionable subito: AI governance/policy applicabile ora, transizione career IT Manager/Tech Lead con insight pratici, tool AI da usare/testare immediatamente, case study PMI replicabile
4 = Alta pertinenza al profilo target: gestione team IT, tech leadership in contesto italiano/europeo, AI applicata in azienda, mercato lavoro per ruoli IT Manager/Tech Lead/CTO
3 = Tech rilevante per il settore ma non specifico: tech generale, soft skills, tendenze, strumenti non direttamente applicabili, articoli informativi su AI/cloud/dev senza focus manageriale
2 = Bassa rilevanza: contenuto generico, marketing, poco applicabile al percorso professionale
1 = Irrilevante

Usa category=job-opportunity quando: l'URL contiene pattern da job listing (linkedin.com/jobs/, indeed.com, glassdoor.com/job, infojobs.it, inpa.gov.it, portali careers con /jobs/ o /careers/ nel path, pagine "Lavora con noi") OPPURE il contenuto descrive una posizione aperta con requisiti, responsabilità e modalità di candidatura.

Per le job-opportunity, applica questa logica di rating in due fasi:
Fase 1 — rilevanza del ruolo: 5=ruolo target (IT Manager, Tech Lead, CTO, AI Engineer), 4=ruolo affine (PM, Dev senior, Cloud Architect), 3=settore giusto ma ruolo distante, 1-2=irrilevante.
Fase 2 — penalità per localizzazione (applica DOPO aver determinato il rating dalla fase 1):
- Full remote o ibrido con presenza opzionale: nessuna penalità.
- In presenza in sede locale/desiderata: nessuna penalità.
- In presenza in sede distante non desiderata: imposta rating = min(2, rating_fase1). Indica la sede nella rating_reason.
- Se la localizzazione non è specificata o non ricavabile dal contenuto: nessuna penalità, segnala "sede non specificata" nella rating_reason.

Se l'utente ha fornito tag, includili in suggested_workflow_tags senza ignorarli.
Se il contenuto estratto è vuoto o insufficiente, basa la classificazione sull'URL e imposta rating=2.
PROMPT;

        $this->seedPrompt(
            'classify-content',
            'Content Classifier',
            'Classifies a piece of content (article, post, link) into a category and rates its relevance for a target professional profile.',
            ['url', 'content', 'tags'],
            $content
        );
    }

    private function seedWeeklyBriefing(): void
    {
        $content = <<<'PROMPT'
Genera un briefing settimanale sintetico dei seguenti contenuti non ancora lavorati.
Per ogni item: titolo, rating, tag, 1 riga di contesto.
Poi una sezione "Top 3 da lavorare questa settimana" con motivazione.
Output in italiano, tono operativo.

Formattazione: usa *testo* per grassetto, niente # per i titoli, usa - per i bullet, non usare --- come separatori (usa righe vuote tra sezioni).
Output compatibile con Telegram Markdown.

CONTENUTI:
{{items}}
PROMPT;

        $this->seedPrompt(
            'weekly-briefing',
            'Weekly Briefing',
            'Generates a weekly digest of pending content items, highlighting the top 3 to process.',
            ['items'],
            $content
        );
    }

    private function seedResearchQuickbrief(): void
    {
        $content = <<<'PROMPT'
Sei un assistente di ricerca per un professionista IT (IT Manager / Tech Lead, focus AI).

L'utente vuole un approfondimento rapido su: {{topic}}

Genera un quick brief strutturato in italiano. Sii concreto e operativo.

*Cos'è:* 1-2 frasi
*Perché è rilevante* (per IT Manager / AI transition in contesto PMI): 2-3 punti
*Aspetti chiave da esplorare:* 3-5 bullet
*Risorse consigliate:* 2-3 titoli o tool (solo se li conosci con certezza)
*Azione suggerita:* 1 riga

Formattazione: usa *testo* per grassetto, niente # per i titoli, usa - per i bullet, non usare --- come separatori (usa righe vuote tra sezioni).
Output compatibile con Telegram Markdown.

Max 200 parole totali. Niente buzzword.
PROMPT;

        $this->seedPrompt(
            'research-quickbrief',
            'Research Quick Brief',
            'Produces a concise research brief (≤200 words) on a given topic, tailored for an IT Manager audience.',
            ['topic'],
            $content
        );
    }

    private function seedResearchDeepdive(): void
    {
        $content = <<<'PROMPT'
Sei un assistente di ricerca per un professionista IT (IT Manager / Tech Lead, focus AI).

Genera un approfondimento completo su: {{topic}}

Struttura in italiano, formattazione compatibile con Telegram (*grassetto*, niente #, niente --- come separatori):

*Panoramica*
Cos'è, a cosa serve, chi lo usa.

*Casi d'uso rilevanti*
Applicazioni concrete per IT Manager / PMI con focus AI.

*Confronto con alternative*
2-3 alternative dirette con pro/contro sintetico.

*Pro e contro*
Lista bilanciata.

*Come potrei applicarlo*
Scenari concreti rispetto al profilo (IT Manager, automazioni, produzione contenuti, crescita professionale).

*Risorse per approfondire*
Link, libri, tool, community — solo quelli certi.

Tono: analitico ma pratico. Niente buzzword.
PROMPT;

        $this->seedPrompt(
            'research-deepdive',
            'Research Deep Dive',
            'Generates a full-length research report on a given topic, structured for an IT Manager with AI focus.',
            ['topic'],
            $content
        );
    }

    private function seedJobFilter(): void
    {
        $content = <<<'PROMPT'
Sei un filtro di rilevanza per offerte di lavoro. Valuta se questa posizione è adatta al profilo target.

PROFILO TARGET:
- Ruoli cercati: IT Manager, Technical Account Manager, ibrido AI+management, CTO PMI
- Settori OK: tech, consulenza, PMI strutturate, grandi aziende (utilities, finance, retail)
- Competenze chiave: project management IT, AI e automazioni, gestione team, stakeholder management
- ESCLUDI: DevOps puro, SRE, posizioni esclusivamente coding, manifatturiero non tech, stage/tirocinio

OFFERTA:
- Azienda: {{company}}
- Titolo: {{title}}
- Sede: {{city}} ({{country}})
- Pubblicata: {{date}}
- Descrizione: {{description}}

Rispondi ESCLUSIVAMENTE con JSON valido:
{"rilevante":true,"score":4,"motivo":"max 100 caratteri","category_hint":"IT Manager|Tech Lead|Technical Account|AI|Concorso|Altro"}

Score 1–5: 5=IT Manager/ibrido AI+management, 4=Tech Lead/Technical Account, 3=dev lead gestionale, 2=dev senior puro, 1=Irrilevante
PROMPT;

        $this->seedPrompt(
            'job-filter',
            'Job Relevance Filter',
            'Scores a job posting 1–5 for relevance against a target professional profile (IT Manager / Tech Lead with AI focus).',
            ['company', 'title', 'city', 'country', 'date', 'description'],
            $content
        );
    }
}
