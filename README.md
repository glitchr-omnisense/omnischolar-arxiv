# omnischolar/arxiv

arXiv for [glitchr/omnischolar](https://github.com/glitchr-studio/omnischolar): preprints - an
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

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
