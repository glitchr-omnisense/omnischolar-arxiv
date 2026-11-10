# omnischolar/arxiv

arXiv for [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): preprints - an
author's by name, one by its arXiv id, a search by text, title, author, category and year - with
their abstracts, categories, PDF, and the DOI and journal reference of the published version
when the authors added them. No key; three seconds between two calls, as arXiv asks.

```php
$arxiv = (new ArxivSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$arxiv->works('Juan Maldacena');
$arxiv->work('hep-th/9711200');
$arxiv->search(new Query(text: 'wormholes', domains: ['hep-th', 'gr-qc'], from: 2024, to: 2024));
```

```yaml
omnischolar:
    sources:
        arxiv: { factory: arxiv, options: { mailto: 'support@glitchr.io', throttle: 3 } }
```

See [docs/](docs/index.md).

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://info.arxiv.org/help/api/tou.html on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnischolar/blob/1.x/docs/policies.md)).

- Limits: 1 every 3 seconds; at most 1 in flight.
- Licence: CC0-1.0; commercial use: allowed.
- Credit: not required.
- Needs: nothing.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by arXiv or Cornell University. It reaches their service on behalf of whoever runs it, and
it does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the arXiv API's terms
([Terms of Use for arXiv APIs](https://info.arxiv.org/help/api/tou.html)), for obtaining whatever
they require (an account, a key, a licence or subscription, an attribution or a citation, a rate
limit to respect), and for any use made of what it returns. The authors publish it free of charge,
make no commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
