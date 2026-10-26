# omnischolar/arxiv

arXiv for [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): preprints -
an author's by name, one by its arXiv id, a search - with their abstracts, categories, PDF, and
the DOI and journal reference of the published version when the authors added them. The source
calls arXiv's API, which answers in Atom, without a key:

| Operation | Request |
|---|---|
| `works($name)` | `GET https://export.arxiv.org/api/query?search_query=au:"<name>"&start=&max_results=&sortBy=&sortOrder=` |
| `work($id)` | `GET .../query?id_list=<id>&max_results=1` |
| `search($query)` | `GET .../query?search_query=all:...+AND+ti:...+AND+au:...` |
| `author()` | not supported: arXiv has no author profiles |

The base address is `ArxivSource::BASE_URI` (`https://export.arxiv.org/api/`). There is no
sandbox.

## Installation

```sh
composer require omnischolar/arxiv
```

Requires PHP 8.2, `ext-dom` (the answers are Atom), `omnisense/omnischolar` (1.x) and
`symfony/http-client` (6.4, 7 or 8). In a Symfony application with `OmnischolarBundle`, the
factory is registered as soon as the package is installed.

## Credentials

None. arXiv asks for one call every three seconds: the default `throttle` leaves them. A `mailto`
names the caller in the `User-Agent`.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `throttle` | float | `3.0` | no | seconds between two calls of the source |
| `mailto` | string | `null` | no | added to the `User-Agent` |
| `user_agent` | string | `omnischolar/1.x (+https://github.com/glitchr-omnisense/omnischolar)` | no | |
| `base_uri` | string | `https://export.arxiv.org/api/` | no | |

## Capabilities

`capabilities()`: `works`, `work`, `search`, `abstracts`, `open_access`, `domains`.

| Operation | Supported | Reads |
|---|---|---|
| `author()` | no (`NotSupportedException`: `does not know authors`) | |
| `works()` | yes | a name as signed: `Juan Maldacena`, `Maldacena, Juan`; an `Identifier` is refused |
| `work()` | yes | an arXiv id, old (`hep-th/9711200`) or new (`2401.12345`) style, with or without its version, or its abs/pdf address |
| `search()` | yes | `text` (`all:`), `title` (`ti:`), `author` (`au:`); none: `all:*` |

## Behaviour

**Query.** Words with a space are sent in double quotes as one term; the terms are joined by
`AND`. `domains` adds `(cat:hep-th OR cat:gr-qc)`; `from`/`to` add `submittedDate:[<from>01010000
TO <to>12312359]` (1991 and 2999 for an open end); `types` without `PREPRINT` gives an empty page
without a call; `openAccess` is not read (every preprint is open). `sort`: `relevance` →
`sortBy=relevance`, any other → `submittedDate`, descending unless `oldest`. `limit` 1 to 2000
(`ArxivSource::MAX_RESULTS`); the cursor is an offset (`start`); `Page::$total` is
`opensearch:totalResults`.

**A preprint.** Title, type `preprint`, authors (and their affiliations when given), year and
date of first submission (`published`), venue `arXiv` (a repository), abstract, `openAccess`
true, the abstract page as `url`, the PDF (`link[@title="pdf"]`), the categories as `domains`
(the primary one first); identifiers: the arXiv id (without version) and the DOI the authors
added; the journal reference (or else the comment) as `note`. No citations.

**Errors in the feed.** arXiv answers a malformed query with 200 and an "Error" entry: a
`ProviderException` with its summary (status 400). An answer that is not Atom is a
`ProviderException`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnischolar\Arxiv\ArxivSourceFactory;
use Omnischolar\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$arxiv = (new ArxivSourceFactory(HttpClient::create()))->create(['mailto' => getenv('OMNISCHOLAR_MAILTO') ?: null]);

$w = $arxiv->work('hep-th/9711200');
echo $w->title, "\n", implode(', ', array_map(fn ($a) => $a->name, $w->authors)), "; ", $w->date, "; DOI ", $w->doi(), "\n  note: ", $w->note, "\n  pdf: ", $w->pdfUrl, "\n";

$page = $arxiv->search(new Query(title: 'wormholes', domains: ['hep-th'], from: 2025, limit: 3));
echo $page->total, " found, next ", var_export($page->next, true), "\n";
foreach ($page as $work) {
    printf("  %s  %-10s %s - %s\n", $work->date, $work->type->value, mb_substr($work->fullTitle(), 0, 80), $work->venue->name);
}
```

```
The Large N Limit of Superconformal Field Theories and Supergravity
Juan M. Maldacena; 1997-11-27; DOI 10.1023/a:1026654312961
  note: Adv.Theor.Math.Phys.2:231-252,1998
  pdf: https://arxiv.org/pdf/hep-th/9711200v3
166 found, next '3'
  2026-10-23  preprint   Entanglement entropy of evolving wormholes - arXiv
  2026-10-16  preprint   Scalar Casimir Effect from Bilocal Correlations in a Wormhole Foam - arXiv
  2026-10-15  preprint   Dilaton-Axion Wormhole Partition Function Away from the BPS Endpoint - arXiv
```

(called live on 2026-10-26 at 14:18 UTC, from the core's harness image, without a `mailto`)

In Symfony:

```yaml
omnischolar:
    sources:
        arxiv: { factory: arxiv, options: { mailto: '%env(OMNISCHOLAR_MAILTO)%' } }
```

## Errors

| Case | Result |
|---|---|
| an unknown id | `null` |
| `author()`; an `Identifier` given to `works()`; `work()` of anything but an arXiv id | `NotSupportedException` |
| a malformed query (an "Error" entry), an answer that is not Atom, another 4xx | `ProviderException` |
| a 429 | `RateLimitedException` |
| a 5xx, a timeout | `UnavailableException` |

## Verified, and not

Tested offline: `Tests/ArxivSourceTest.php` (6 tests), on `MockHttpClient` with answers recorded
on 2026-10-04 - ten of Juan Maldacena's preprints (167), hep-th/9711200 with its DOI and journal
reference, a search in hep-th and gr-qc for 2024, an error answer. Called live on 2026-10-04 and
on 2026-10-26 (the example above).

Not given by arXiv: author identifiers (by name only, as signed), citations, the DOI of the
published version unless the authors added it - the Merger also joins a preprint and its
publication by title, in its year or the next.

## Responsibility

An independent, unofficial client, not affiliated with arXiv or Cornell University. Whoever runs
it complies with arXiv's terms ([Terms of Use for arXiv APIs](https://info.arxiv.org/help/api/tou.html)),
one call every three seconds included: see the README's Responsibility section.
