# Covenant Interlinear

A Laravel 13 application for reading, importing, editing, searching, and printing the Hebrew–English Covenant of Christ interlinear.

## Requirements

- PHP 8.5 with SQLite and Intl
- Composer
- Node.js 22+ and npm

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan admin:create
```

Run the application with `composer run dev`, then visit the reader at `/` and Filament at `/admin`. Public registration is disabled; create or promote administrators with `php artisan admin:create`.

## Scripture imports

The approved source is restricted in code to `https://brithamashiach.org`. Its Angular frontend exposes structured data rather than server-rendering scripture:

- `GET /api/volume/books` — verified book identifiers and chapter lists
- `GET /api/volume/books/{slug}/chapters/{number}` — sentence-level Hebrew and Covenant of Christ English

The chapter payload includes pointed and unpointed Hebrew plus proper-name / Hebrew-Bible occurrence flags. A separate `/api/volume/morphology/{word}` endpoint provides verified Strong’s IDs, roots, definitions, and morphology for recognized Hebrew-Bible forms. It does not provide contextual literal glosses, transliterations, or word alignment, so those fields remain unresolved until an administrator supplies verified data.

Import from Filament’s **Scripture → Import Scripture** page or Artisan:

```bash
php artisan scripture:import 1nefi 1
php artisan scripture:import 1nefi --all
php artisan scripture:import 1nefi 1 --file=/absolute/path/chapter.json
```

Each chapter imports in its own database transaction. Reimports are idempotent, preserve existing word annotations, skip manually edited passages, and flag changed annotated Hebrew for explicit retokenization.

## Editing and reading

- Books, chapters, passages, lemmas, and word glosses are managed in Filament.
- The **Lookup lexicon** word action imports a verified dictionary entry for one recognized form without overwriting its contextual gloss.
- Editing Hebrew marks a passage as requiring retokenization. The explicit **Retokenize** action warns when unmatched annotations may be removed.
- Only published chapters appear in the reader, concordance, word-study occurrences, and print view.
- Use the reader controls for glosses, transliteration, English visibility, type size, and printing.

## Quality checks

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
npm run check
npm run types:check
npm run build
```
