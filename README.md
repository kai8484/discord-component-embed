# DiscordComponentEmbed

A MediaWiki extension that gives wiki page links a rich preview: a titled card with
a description, an optional image, a gallery and a row of link buttons.

It works on every page out of the box, with no markup: each article gets a preview
derived from its title, its intro and, when the PageImages extension is installed,
its thumbnail. Authors override any of it with `{{#discord_embed: ...}}`, or from a
Lua module through `mw.ext.discordembed` when Scribunto is installed.

The extension produces metadata only. It never talks to Discord: no bot, no
application, no API token, no gateway connection. It writes a JSON payload of
Discord components into the page head, and fills in Open Graph tags for the keys no
other extension on the wiki already emits.

## Requirements

- MediaWiki 1.46 or later (declared in `extension.json`, so an older wiki refuses to
  load the extension instead of failing later)
- PHP 8.3 or later
- the `dom` and `mbstring` PHP extensions (`mbstring` is not optional: the
  character-counted limits are wrong without it)
- optional: [PageImages](https://www.mediawiki.org/wiki/Extension:PageImages) for
  article thumbnails
- optional: [Scribunto](https://www.mediawiki.org/wiki/Extension:Scribunto) to let Lua
  modules declare previews

## Installation

```bash
cd $IP/extensions
git clone https://github.com/kai8484/discord-component-embed.git DiscordComponentEmbed
```

```php
// LocalSettings.php
wfLoadExtension( 'DiscordComponentEmbed' );
```

## Settings

| setting | default | meaning |
| --- | --- | --- |
| `$wgDiscordComponentEmbedEnabled` | `true` | Publish previews at all. |
| `$wgDiscordComponentEmbedDescriptionBudget` | `300` | Character budget for a description, applied to the payload and to `og:description` alike. |
| `$wgDiscordComponentEmbedThumbnailWidth` | `1200` | Width preview thumbnails are rendered at. The same bound PageImages uses for its own `og:image`. |
| `$wgDiscordComponentEmbedNamespaces` | `null` | Namespace IDs that get previews, as returned by `NamespaceInfo::getValidNamespaces()`. `null` means every namespace. |
| `$wgDiscordComponentEmbedOpenGraph` | `true` | Add Open Graph fallback tags for keys no other source on the wiki emits. |
| `$wgDiscordComponentEmbedComponents` | `true` | Publish the component payload. Set it to `false` to let Discord render its own card from the Open Graph tags instead: a larger clickable title, the description and the image, but no buttons, gallery or accent. |
| `$wgDiscordComponentEmbedTitleHeading` | `0` | Heading level for the card's header, `0` for body size, `1`–`3` for a heading. A component's text is sized by Discord, so this is the only way to make the title bigger. |

## Example

```wikitext
{{#discord_embed:
 | title = Doorman
 | description = A game about doors, and the people who open them.
 | image = File:Doorman cover.png
 | image-description = Cover art
 | gallery1 = File:Screenshot 1.png
 | gallery1-description = The first room
 | button1 = [[Doorman|Read more]]
 | button2 = https://example.com/patch-notes{{!}}Patch notes
 | accent = #5865F2
}}
```

Every argument is documented in [docs/Authoring.md](docs/Authoring.md); how to build
and test the extension is in [docs/Development.md](docs/Development.md).

## Things the wiki has to get right

- **A protocol-relative `$wgServer` is read as `https://`.** Previews carry absolute
  URLs and Discord refuses a link or a button without a scheme, so a wiki set to
  `//wiki.example.com` still produces `https://` links here.
- **Thumbnails are rendered during the page save** unless the wiki sets
  `$wgGenerateThumbnailOnParse = false`. With the default, the first save of a page
  that names a new image renders that thumbnail in the save request itself.
- **Parsoid pages get no preview.** The preview is built in `ParserAfterTidy`, which
  only the legacy parser fires; a wiki that serves Parsoid output for page views
  loses previews silently rather than erroring.

## Licence

Released into the public domain under [The Unlicense](COPYING).
