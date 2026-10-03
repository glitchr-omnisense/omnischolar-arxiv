# omnischolar/arxiv

## Installation

```sh
composer require omnischolar/arxiv
```

Needs `ext-dom` (the API answers in Atom).

```php
use Omnischolar\Arxiv\ArxivSourceFactory;

$arxiv = (new ArxivSourceFactory($httpClient))->create(['mailto' => 'support@glitchr.io']);
```

## Calls

| Method | Reads | arXiv API (`export.arxiv.org/api/query`) |
|---|---|---|
| `works()` | a name ("Juan Maldacena", "Maldacena, Juan") | `search_query=au:"..."` |
| `work()` | an arXiv id, old or new style, with or without its version | `id_list=` |
| `search()` | `text` (`all:`), `title` (`ti:`), `author` (`au:`) | `search_query=` |
| `author()` | - | not supported: arXiv has no author profiles |

`Query`: `from`/`to` (`submittedDate`), `domains` (arXiv categories: `cat:hep-th OR
cat:gr-qc`), `types` (preprints only: any other type gives an empty page), `sort` (`newest`,
`oldest` by submission date, `relevance`), `limit` (2 000 at most), `cursor` (an offset).

## What a preprint holds

Title, type `preprint`, authors (and their affiliations when given), year and date of first
submission, venue "arXiv", abstract, open access, the abstract page, the PDF, categories as
domains (the primary one first); identifiers: arXiv id (without version) and the DOI the authors
added; the journal reference (or else the comment) as `note`.

## What arXiv does not give

No author identifiers: by name only, as signed. No citations. The DOI of the published version
only when the authors added it: the Merger also joins a preprint and its publication by title, in
its year or the next. A malformed query is answered 400 with an "Error" entry
(`ProviderException`).

## Tests

Recorded on 2026-10-04: ten of Juan Maldacena's preprints (167), hep-th/9711200 with its DOI
and journal reference, a search in hep-th and gr-qc for 2024, an error answer.
