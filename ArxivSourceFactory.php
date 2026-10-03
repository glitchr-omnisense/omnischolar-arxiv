<?php

namespace Omnischolar\Arxiv;

use Omnischolar\Config;
use Omnischolar\Source\SourceFactory;
use Omnischolar\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * arXiv: preprints, by author name, id or search - no key.
 *
 *   options:
 *     throttle: 3                                # seconds between two calls, as arXiv asks
 *     mailto: 'support@glitchr.io'               # in the User-Agent
 *     base_uri: 'https://export.arxiv.org/api/'
 */
final class ArxivSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnischolar.factory_name' => 'arxiv',
            'omnischolar.required_options' => [],
            'throttle' => 3.0,
            'base_uri' => ArxivSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new ArxivSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', ArxivSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle', 3.0),
        );
    }
}
