# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release. Omnischolar arXiv: preprints by author name, arXiv id or search, with abstracts, categories, PDFs and the DOI of the published version - no key, three seconds between calls.

### Changed

- Requires `omnisense/omnischolar: ^1.0`: stable versions, so `composer require omnischolar/arxiv` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
