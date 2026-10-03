<?php

namespace Omnischolar\Arxiv;

use Omnischolar\Exception\NotSupportedException;
use Omnischolar\Exception\ProviderException;
use Omnischolar\Model\Contributor;
use Omnischolar\Model\Identifier;
use Omnischolar\Model\Identifiers;
use Omnischolar\Model\Scheme;
use Omnischolar\Model\Venue;
use Omnischolar\Model\Work;
use Omnischolar\Model\WorkType;
use Omnischolar\Source\Capability;
use Omnischolar\Source\HttpSource;
use Omnischolar\Source\Page;
use Omnischolar\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * arXiv (export.arxiv.org/api, Atom, no key): preprints - an author's by
 * name, one by its arXiv id (old "hep-th/9711200" or new "2401.12345"
 * style), a search - with their abstracts, categories, PDF, and the DOI and
 * journal reference of the published version when the authors added them.
 *
 * arXiv knows no author profiles, and asks for one call every three
 * seconds: the throttle leaves them.
 */
final class ArxivSource extends HttpSource
{
    public const BASE_URI = 'https://export.arxiv.org/api/';

    /** The most entries arXiv serves per call. */
    public const MAX_RESULTS = 2000;

    private const ATOM = 'http://www.w3.org/2005/Atom';
    private const ARXIV = 'http://arxiv.org/schemas/atom';
    private const OPENSEARCH = 'http://a9.com/-/spec/opensearch/1.1/';

    public function __construct(
        HttpClientInterface $http,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 3.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'arxiv';
    }

    public function capabilities(): array
    {
        return [Capability::WORKS, Capability::WORK, Capability::SEARCH, Capability::ABSTRACTS, Capability::OPEN_ACCESS, Capability::DOMAINS];
    }

    /** An author's preprints, by the name they sign ("Juan Maldacena", "Maldacena, Juan"). The cursor is an offset. */
    public function works(Identifier|string $author, ?Query $query = null): Page
    {
        if ($author instanceof Identifier) {
            throw NotSupportedException::identifier('arxiv', (string) $author, 'list the works of (arXiv searches by name)');
        }

        return $this->list($query ?? new Query(), ['au:'.self::phrase($author)]);
    }

    public function work(Identifier|string $id): ?Work
    {
        $arxiv = self::identify($id, Scheme::ARXIV) ?? throw NotSupportedException::identifier('arxiv', (string) $id);
        $page = $this->feed(['id_list' => $arxiv->value, 'max_results' => 1]);

        return $page->works[0] ?? null;
    }

    public function search(Query $query): Page
    {
        $terms = [];
        if (null !== $query->text && '' !== trim($query->text)) {
            $terms[] = 'all:'.self::phrase($query->text);
        }
        if (null !== $query->title) {
            $terms[] = 'ti:'.self::phrase($query->title);
        }
        if (null !== $query->author) {
            $terms[] = 'au:'.self::phrase($query->author);
        }

        return $this->list($query, $terms ?: ['all:*']);
    }

    /** @param list<string> $terms */
    private function list(Query $query, array $terms): Page
    {
        if ($query->domains) {
            $terms[] = '('.implode(' OR ', array_map(static fn (string $c) => 'cat:'.$c, $query->domains)).')';
        }
        if (null !== $query->from || null !== $query->to) {
            $terms[] = \sprintf('submittedDate:[%s TO %s]', ($query->from ?? 1991).'01010000', ($query->to ?? 2999).'12312359');
        }
        if ($query->types && !\in_array(WorkType::PREPRINT, $query->types, true)) {
            return new Page([], null, 0);
        }
        $offset = max(0, (int) $query->cursor);
        $limit = max(1, min(self::MAX_RESULTS, $query->limit));

        return $this->feed([
            'search_query' => implode(' AND ', $terms),
            'start' => $offset,
            'max_results' => $limit,
            'sortBy' => Query::RELEVANCE === $query->sort ? 'relevance' : 'submittedDate',
            'sortOrder' => Query::OLDEST === $query->sort ? 'ascending' : 'descending',
        ], $offset, $limit);
    }

    /** @param array<string, mixed> $parameters */
    private function feed(array $parameters, int $offset = 0, int $limit = 1): Page
    {
        $xml = $this->get('query', $parameters, ['Accept' => 'application/atom+xml']);
        if (null === $xml) {
            return new Page([], null, 0);
        }
        $document = new \DOMDocument();
        if (!@$document->loadXML($xml, \LIBXML_NONET)) {
            throw new ProviderException('arxiv', 'the answer is not an Atom feed');
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('a', self::ATOM);
        $xpath->registerNamespace('arxiv', self::ARXIV);
        $xpath->registerNamespace('os', self::OPENSEARCH);

        $works = [];
        foreach ($xpath->query('/a:feed/a:entry') ?: [] as $entry) {
            $id = self::value($xpath, 'a:id', $entry);
            if (str_contains((string) $id, '/api/errors')) {
                // arXiv answers a malformed query with 200 and an "Error" entry.
                throw new ProviderException('arxiv', self::value($xpath, 'a:summary', $entry) ?? 'malformed query', 400);
            }
            $works[] = $this->toWork($xpath, $entry);
        }
        $total = (int) self::value($xpath, '/a:feed/os:totalResults');
        $next = $offset + $limit;

        return new Page($works, $works && $next < $total ? (string) $next : null, $total);
    }

    private function toWork(\DOMXPath $xpath, \DOMNode $entry): Work
    {
        $arxiv = Identifier::tryOf(Scheme::ARXIV, (string) self::value($xpath, 'a:id', $entry));
        $authors = [];
        foreach ($xpath->query('a:author', $entry) ?: [] as $author) {
            $affiliations = [];
            foreach ($xpath->query('arxiv:affiliation', $author) ?: [] as $affiliation) {
                $affiliations[] = trim($affiliation->textContent);
            }
            $name = (string) self::value($xpath, 'a:name', $author);
            $parsed = Contributor::fromName($name);
            $authors[] = new Contributor($name, $parsed->given, $parsed->family, affiliations: $affiliations);
        }
        $categories = [];
        if (null !== ($primary = self::value($xpath, 'arxiv:primary_category/@term', $entry))) {
            $categories[] = $primary;
        }
        foreach ($xpath->query('a:category/@term', $entry) ?: [] as $term) {
            $categories[] = $term->nodeValue;
        }
        $published = (string) self::value($xpath, 'a:published', $entry);
        $journal = self::value($xpath, 'arxiv:journal_ref', $entry);
        $comment = self::value($xpath, 'arxiv:comment', $entry);

        return new Work(
            title: self::text(self::value($xpath, 'a:title', $entry)) ?? '',
            type: WorkType::PREPRINT,
            authors: $authors,
            year: '' !== $published ? (int) substr($published, 0, 4) : null,
            date: '' !== $published ? substr($published, 0, 10) : null,
            venue: new Venue('arXiv', Venue::REPOSITORY),
            abstract: self::text(self::value($xpath, 'a:summary', $entry)),
            openAccess: true,
            url: null !== $arxiv ? 'https://arxiv.org/abs/'.$arxiv->value : null,
            pdfUrl: self::value($xpath, 'a:link[@title="pdf"]/@href', $entry),
            domains: array_values(array_unique($categories)),
            identifiers: Identifiers::of($arxiv, Identifier::tryOf(Scheme::DOI, self::value($xpath, 'arxiv:doi', $entry))),
            source: 'arxiv',
            note: null !== $journal ? self::text($journal) : self::text($comment),
        );
    }

    private static function value(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): ?string
    {
        $node = ($xpath->query($expression, $context) ?: null)?->item(0);
        $value = null !== $node ? trim((string) $node->nodeValue) : '';

        return '' === $value ? null : $value;
    }

    /** A name or words as one arXiv term: quoted when there is a space. */
    private static function phrase(string $value): string
    {
        $value = trim(str_replace('"', '', $value));

        return str_contains($value, ' ') ? '"'.$value.'"' : $value;
    }
}
