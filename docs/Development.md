# Building and testing

## Layout

```
extension.json      hooks, services, configuration defaults
src/Config/         the parsed configuration and its validation
src/Content/        intro extraction, markdown, description budget
src/Embed/          resolved content to Discord components, and validation
src/Json/           script-safe JSON encoding
src/Media/          File: titles to public URLs, with the resolution interfaces
src/MediaWiki/      the adapters that use core: links, images, PageImages
src/Output/         publishing: head payload, Open Graph fallback, dedupe
src/Parser/         the parser function, the draft recorder, the collector
src/Scribunto/      the Lua library a module calls, and its hook into Scribunto
tests/phpunit/unit/         no MediaWiki required, run on every change
tests/phpunit/core-contract/ reflects over a real core checkout, no database
tests/phpunit/integration/  parses real wikitext against a real wiki and database
```

The pipeline runs one way: the parser function or the Lua library records what an author
asked for on the `ParserOutput`, `EmbedCollector` compiles that into one configuration
and builds the preview after the page is parsed, and the output hook publishes it.
Nothing downstream writes back.

## Suites

| suite | needs | command |
| --- | --- | --- |
| unit | PHP and composer | `composer test` |
| core-contract | a MediaWiki checkout, no database | `php $IP/vendor/bin/phpunit -c phpunit-core-contract.xml.dist` |
| integration | an installed wiki and a database | `php $IP/vendor/bin/phpunit -c phpunit-integration.xml.dist` |

Run the last two from the extension directory inside an install; they find `$IP` next to
themselves, or set `MW_INSTALL_PATH`.

The unit suite runs against hand-written fakes and must pass with no MediaWiki installed.
CI runs it on every push with a PHP lint and a JSON check. The other two need a wiki and
are run by hand, with the PHPUnit that ships with MediaWiki (9.6 on 1.46) — the
extension's own PHPUnit 11 cannot run them.

The split exists because a fake only confirms the belief that produced it: anything
depending on real `Title` behaviour lives in the integration suite, and the core-contract
suite is what catches a wrong assumption about core before it ships.

## Conventions

MediaWiki's, with `mediawiki/mediawiki-codesniffer` where available. Files are LF,
including tests — a CRLF checkout silently breaks `@group Database`. `.gitattributes`
declares `eol=lf` for that reason.

## Things a wiki operator has to get right

- **Config changes need a purge.** Settings are not part of the parser cache key, so
  cached pages keep their old preview until re-parsed (`purgeParserCache` or an edit).
- **A protocol-relative `$wgServer` is read as `https://`.** Discord refuses links without
  a scheme.
- **Thumbnails render during the page save** unless `$wgGenerateThumbnailOnParse = false`.
- **Parsoid pages get no preview.** The preview is built in `ParserAfterTidy`, which only
  the legacy parser fires.

## Things worth knowing before changing the content layer

- **The extractor sees parser output, never a skin.** The hook receives the parsed page
  body, so there is no `<h1>` for the title; a proxy that feeds it a whole page must cut
  the article body out first.
- **A heading inside furniture does not end the intro.** Infobox and navbox headings are
  skipped, which is why the HTML cannot simply be sliced at the first heading.
- **Markdown is generated, not authored.** Prose is escaped, so any unescaped `*`, `[` or
  backtick in a card comes from the extension itself; that is what makes the generous
  assumptions in `DiscordMarkdown` safe.
- **The description budget counts visible characters**, not source: a link target costs
  nothing, its label costs what a reader sees.
- **Cache dependencies are recorded explicitly.** `EmbedCollector` calls `addImage()` and
  `addLink()` for the authored files and button targets, because a preview references
  them without any wikitext the parser would track.
