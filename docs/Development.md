# Building and testing

## Layout

```
extension.json          manifest: hooks, services, configuration defaults
i18n/                   parser function magic word and messages
src/
  Config/               the parsed configuration and its validation
  Content/              intro extraction, formatting and the description budget
  Embed/                resolved content to Discord components, and validation
  Json/                 script-safe JSON encoding
  Media/                File: titles to public URLs, with the resolution interfaces
  MediaWiki/            the adapters that use core: links, images, PageImages
  Output/               publishing: head payload, Open Graph fallback, dedupe
  Parser/               the parser function and the collector that builds the preview
  Scribunto/            the Lua library a module calls, and its hook into Scribunto
tests/
  stubs/                hand-written fakes for the unit suite
  phpunit/unit/         no MediaWiki required, run on every change
  phpunit/core-contract/ reflects over a real core checkout, no database
  phpunit/integration/  parses real wikitext against a real wiki and database
```

The pipeline is one-directional: the parser function records what an author wrote
into the `ParserOutput`, the collector turns that into one configuration, resolves
it against the page, builds and validates the components, and the output hook
publishes the result. Nothing downstream writes back.

## Three suites, on purpose

| suite | needs | command |
| --- | --- | --- |
| unit | PHP and composer | `composer test` |
| core-contract | a MediaWiki checkout, no database | `php $IP/vendor/bin/phpunit -c phpunit-core-contract.xml.dist` |
| integration | an installed wiki and a database | `php $IP/vendor/bin/phpunit -c phpunit-integration.xml.dist` |

The unit suite runs against hand-written fakes and must pass with no MediaWiki
installed at all; it is the loop to use while changing the configuration, content or
embed layers. CI (`.github/workflows/ci.yml`) runs it on every push, along with a PHP
syntax lint and a JSON check. The other two need a wiki and are run by hand, from the
extension's directory:

```bash
cd $IP/extensions/DiscordComponentEmbed
php $IP/vendor/bin/phpunit -c phpunit-core-contract.xml.dist
php $IP/vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Both find the install next to the extension automatically; set `MW_INSTALL_PATH` to
run them from a checkout that sits somewhere else.

A MediaWiki install needs three things before extension tests can run at all: its
Composer dependencies including dev ones (`composer install` in `$IP`), an
`unzip` binary or the `zip` PHP extension so Composer can unpack packages, and its
generated PHPUnit configuration (`php tests/phpunit/generatePHPUnitConfig.php`,
which writes `$IP/phpunit.xml` and expects `$IP/phpunit.xml.template` to be present —
some distribution images strip that file).

### Why the split

A fake can only confirm the belief that produced it. Anything that depends on real
`Title` behaviour — namespaces, fragments, underscore normalisation, subpage
resolution, interwiki prefixes — lives in the integration suite, where a button's URL
is compared with the href MediaWiki itself renders for the same wikitext. The
core-contract suite is what catches a wrong assumption about core *before* it ships:
it reflects over the real classes, and it asserts that every hook handler the
manifest declares can be built from the services it names, because manifest and
constructor drifting apart is a fatal error on the first page view and invisible in
either file on its own.

## Coding conventions

MediaWiki's conventions, with `mediawiki/mediawiki-codesniffer` if you have it
available (`composer install --dev` in a core checkout provides `phpcs`). Files are
LF, including test files: MediaWiki's `@group Database` check matches the annotation
with a `$`-anchored regex, and a CRLF checkout silently makes it fail. The
`.gitattributes` in this repository declares `eol=lf` for that reason.

## Assumptions checked against MediaWiki 1.46 source

Each of these was confirmed by reading the source or the running install rather than
recalled, and the ones that can be asserted are asserted in the core-contract suite.

| assumption | what 1.46 actually does |
| --- | --- |
| rendered HTML accessor | `ParserOutput::getContentHolderText()`; `getRawText()` is deprecated since 1.45 and there is no wikitext accessor. The collector reads `ParserAfterTidy`'s text instead, which is the finished markup. |
| where per-parse state lives | `ParserOutput::setExtensionData()` / `getExtensionData()`. `appendExtensionData()` only merges string sets and integer sums, which is why several calls are accumulated under one key with `setExtensionData()`. |
| meta tags | `OutputPage::addMeta( $name, $val )` takes exactly two arguments and `getMetaTags()` returns a *list* of `[ name, value ]` pairs. Core turns an `og:` name into `property=` itself. |
| parser function hook | `MediaWiki\Parser\Hook\ParserFirstCallInitHook`, and handlers receive their declared services positionally. |
| hook order | `BeforePageDisplay` runs *before* `SkinTemplate::outputPage()` calls `initPage()`, which is what adds core's `og:` tags. A presence check at that point cannot see them, which is why the extension decides from configuration and deduplicates later instead. |
| the late hook | `OutputPageAfterGetHeadLinksArray` is the point where every source's tags are present at once; the extension drops its own tag there when another source emitted the same key. |
| PageImages | service key `PageImages.PageImages`, `getImage( Title ): ?File`; its `og:image` is a 1200×1200 transform expanded to the canonical URL, and `$wgPageImagesOpenGraph` defaults to true. |
| namespace shapes | `NamespaceInfo::getValidNamespaces()` returns a list of IDs; `getCanonicalNamespaces()` returns an ID-to-name map. |
| subpages | `NamespaceInfo::hasSubpages( int )` is a namespace question; `Title::isSubpage()` is a page question. Relative link resolution delegates to `Linker::normalizeSubpageLink()`, which is what the parser itself calls. |
| `Title::getFullURL()` | includes the fragment and expands against `$wgServer`, so it is absolute whatever the article path looks like. A link that is only a fragment is a self-link in core (`#Fragment`), so the extension builds it from the context page instead. |
| `RepoGroup::findFile()` | returns `File|false`, not `?File`, and 1.46 declares no return type; the adapter maps `false` to `null`. |
| pipe trick | applied at **save** time by `Parser::pstPass2()`, and only to context forms. Plain `[[Foo|]]` survives into stored text, so an empty label still has to be treated as "no label". |

## What the extractor treats as the intro

- The paragraphs before the first heading. If the lead is written as a list (or a
  definition list), the items are used instead: a list is a fallback, not an
  invitation, because roster and index pages open with a sentence and then list
  everything they link to. Paragraphs are chosen over a list whenever the page opens
  with one or they sit as shallow as the list; a page that opens with a list keeps it.
- Of the kind chosen, the shallowest wins. Template grids and card layouts are
  nested containers full of paragraphs, so candidates are compared against the page
  itself rather than against an assumed structure.
- Refused outright: infoboxes, navboxes, hatnotes, maintenance and metadata boxes,
  sidebars, `thumb`, galleries, tables of contents, `noprint`, tables, figures and
  their captions, blockquotes (where template flavour quotes live), collapsibles
  (`.mw-collapsible`, `.mw-collapsed`), empty elements (`.mw-empty-elt`),
  edit-section links, reference markers, `<nav>` and `<aside>`, the `navigation`,
  `complementary`, `banner` and `contentinfo` roles, and anything carrying
  `display:none`, `visibility:hidden`, the `hidden` attribute or
  `aria-hidden="true"`.
- Links survive as masked Discord links, with parser output's relative hrefs
  expanded against the wiki's server and the page they came from: a preview leaves
  the wiki, so its links have to leave it too.

Checked against a live wiki (MediaWiki 1.46 with PageImages and TextExtracts):
21 of 22 real articles produced an intro character-identical to that wiki's own
TextExtracts answer. The divergences are deliberate — a `<dl><dd>` "See also:" line
is left out because the lead's paragraphs take precedence over list items (it is not
refused as furniture, so a lead with no paragraph would include it), and a flavour
quote in a blockquote is not read as prose.
Text hidden by *site-specific* CSS that uses none of the markers above can still
reach the description: inline styles are covered, a custom stylesheet is not.

## Testing against real articles

The unit and integration suites use short, tidy fixtures, which is exactly where
intro extraction looks easy. For real confidence, run the extraction over populated
articles from a live wiki: fetch them through the wiki's own `action=parse` API
(what the extension is handed in production is parsed wikitext, not a whole page),
take the article image from `prop=pageimages`, and compare the result with the same
wiki's `prop=extracts&exintro&explaintext`. A small read-only shim outside this
repository is enough; two bugs — template flavour quotes read as the lead, and
roster pages read as a wall of names — were only visible that way.

## Design decisions worth knowing

- **Description parity.** The intro is extracted and bounded once, at the same
  budget for both consumers. `og:description` gets the plain rendering and the
  payload gets the same text with Discord markdown applied; the two renderings are
  truncated independently, so markdown markers can move the cut by a word without
  either one passing the budget.
- **Open Graph deferral.** The extension emits `og:title`, `og:description`,
  `og:type`, `og:url` and, when nothing else claims it, `og:image` with dimensions.
  Keys that `$wgSkinMetaTags` says the skin will emit, and `og:image` when PageImages'
  own Open Graph support is on, are left alone. This is decided from configuration
  because hook order makes a presence check unreliable, and a duplicate is then
  removed at the last moment in favour of the other source.
- **Failure is silent on the page, loud in the log.** An unusable configuration
  publishes nothing at all — no payload, no fallback tags — and logs the reason at
  warning level on the `DiscordComponentEmbed` channel. Dropped files and dropped
  buttons log at debug level, because leaving those out is the documented behaviour.
- **Button rows.** Five buttons per row and five rows is Discord's own limit, which
  is where the 25-button cap comes from. A payload that would exceed Discord's 40
  components gives up gallery items before it gives up buttons.
- **Stored preview.** The record in the `ParserOutput` is a plain array with a
  `version` field, not a serialised object: a cached `ParserOutput` outlives a
  deployment, and an unreadable or older record simply means no preview.
