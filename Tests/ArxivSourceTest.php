<?php

namespace Omnischolar\Arxiv\Tests;

use Omnischolar\Arxiv\ArxivSourceFactory;
use Omnischolar\Exception\NotSupportedException;
use Omnischolar\Exception\ProviderException;
use Omnischolar\Model\Identifier;
use Omnischolar\Model\Scheme;
use Omnischolar\Model\WorkType;
use Omnischolar\Source\Capability;
use Omnischolar\Source\Query;
use Omnischolar\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from export.arxiv.org/api on 2026-10-04: works-author.xml
 * (search_query=au:"Juan Maldacena", ten, newest first), work-id.xml
 * (id_list=hep-th/9711200), search.xml (all:"wormholes" in hep-th or
 * gr-qc, submitted in 2024), error.xml (id_list=1234.x, answered 400).
 */
final class ArxivSourceTest extends TestCase
{
    /** @var list<array<string, string>> */
    private array $calls = [];

    private function source(?MockResponse $response = null): SourceInterface
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $this->calls[] = $query;
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                'hep-th/9711200' === ($query['id_list'] ?? null) => 'work-id.xml',
                str_starts_with($query['search_query'] ?? '', 'au:"Juan Maldacena"') => 'works-author.xml',
                str_starts_with($query['search_query'] ?? '', 'all:wormholes') => 'search.xml',
                default => null,
            };

            return $fixture ? new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture)) : new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/error.xml'), ['http_code' => 400]);
        });

        return (new ArxivSourceFactory($http))->create(['throttle' => 0]);
    }

    public function testAnAuthorsPreprintsByName(): void
    {
        $page = $this->source()->works('Juan Maldacena', new Query(limit: 10));

        self::assertSame(167, $page->total);
        self::assertCount(10, $page);
        self::assertSame('10', $page->next, 'an offset');
        $first = $page->works[0];
        self::assertSame('Logarithmic gravity from a very slow roll limit of inflation', $first->title);
        self::assertSame(WorkType::PREPRINT, $first->type);
        self::assertSame('2609.38052', $first->id(Scheme::ARXIV), 'without its version');
        self::assertSame('2026-09-29', $first->date);
        self::assertSame(['hep-th', 'gr-qc'], $first->domains, 'the primary category first');
        self::assertSame('https://arxiv.org/pdf/2609.38052v1', $first->pdfUrl);
        self::assertSame('https://arxiv.org/abs/2609.38052', $first->url);
        self::assertTrue($first->openAccess);
        self::assertSame(['Jordan Cotler', 'Victor Ivo', 'Juan Maldacena'], array_map(static fn ($c) => $c->name, $first->authors));
        self::assertSame('Maldacena', $first->authors[2]->family);
        self::assertStringStartsWith('We study the inflationary no-boundary wavefunction', $first->abstract);

        self::assertSame('au:"Juan Maldacena"', $this->calls[0]['search_query']);
        self::assertSame('submittedDate', $this->calls[0]['sortBy']);
        self::assertSame('descending', $this->calls[0]['sortOrder']);
    }

    public function testOneByItsIdWithThePublishedVersion(): void
    {
        $work = $this->source()->work('arXiv:hep-th/9711200v3');

        self::assertSame('The Large N Limit of Superconformal Field Theories and Supergravity', $work->title);
        self::assertSame('hep-th/9711200', $work->id(Scheme::ARXIV));
        self::assertSame('10.1023/a:1026654312961', $work->doi(), 'the DOI the authors added');
        self::assertSame('Adv.Theor.Math.Phys.2:231-252,1998', $work->note, 'the journal reference');
        self::assertSame(1997, $work->year, 'first submitted');
        self::assertSame('hep-th/9711200', $this->calls[0]['id_list']);
    }

    public function testASearchInCategoriesAndYears(): void
    {
        $page = $this->source()->search(new Query(text: 'wormholes', from: 2024, to: 2024, domains: ['hep-th', 'gr-qc'], limit: 3));

        self::assertSame(241, $page->total);
        self::assertCount(3, $page);
        self::assertSame('all:wormholes AND (cat:hep-th OR cat:gr-qc) AND submittedDate:[202401010000 TO 202412312359]', $this->calls[0]['search_query']);
        self::assertSame('10.1007/jhep12(2025)090', $page->works[2]->doi());
        self::assertContains(Capability::DOMAINS, $this->source()->capabilities());
    }

    public function testErrors(): void
    {
        try {
            $this->source()->work('2401.99999');
            self::fail();
        } catch (ProviderException $e) {
            self::assertSame(400, $e->status);
        }
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('incorrect id format');
        $this->source(new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/error.xml')))->work('2401.99999');
    }

    public function testNoAuthorsNorOtherIdentifiers(): void
    {
        try {
            $this->source()->author('Juan Maldacena');
            self::fail();
        } catch (NotSupportedException) {
        }
        $this->expectException(NotSupportedException::class);
        $this->source()->works(Identifier::orcid('0000-0002-9127-1687'));
    }

    public function testTheThrottleLeavesThreeSeconds(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/work-id.xml')));
        $source = (new ArxivSourceFactory($http))->create(['throttle' => 0.3]);
        $start = microtime(true);
        $source->work('hep-th/9711200');
        $source->work('hep-th/9711200');
        self::assertGreaterThanOrEqual(0.29, microtime(true) - $start);
    }
}
