# DiscordComponentEmbed

A MediaWiki extension that gives wiki links a Discord card: a clickable title, a
description, a thumbnail, and a small footer naming the wiki. It writes metadata only —
no bot, no token, no gateway.

## Requirements

- MediaWiki 1.46+
- PHP 8.3+ with `dom` and `mbstring`
- optional: PageImages (automatic thumbnails), Scribunto (Lua authoring)

## Installation

```bash
cd $IP/extensions
git clone https://github.com/kai8484/discord-component-embed.git DiscordComponentEmbed
```

```php
wfLoadExtension( 'DiscordComponentEmbed' );
```

## Usage

Every page gets a preview with no markup: the title is the page title, the description is
the article intro, the thumbnail comes from PageImages. Authors override any of it — see
[Authoring](docs/Authoring.md).

```wikitext
{{#discord_embed:
 | title = Doorman
 | description = Doorman is a hero from Deadlock.
 | image = File:Doorman cover.png
 | accent = #5865F2
}}
```

The card is one Discord container: the title as a heading link, the description as plain
prose (link labels survive, their targets do not), a section thumbnail, and — under a
divider — the wiki's mark and name.

## Settings

| setting | default | meaning |
| --- | --- | --- |
| `$wgDiscordComponentEmbedEnabled` | `true` | Publish previews at all. |
| `$wgDiscordComponentEmbedComponents` | `true` | Publish the component payload. `false` leaves only Open Graph tags, so Discord draws its own card: bigger clickable title, no thumbnail, divider or footer. |
| `$wgDiscordComponentEmbedTitleHeading` | `2` | Header size: `0` body text, `1`–`3` heading levels (24/20/16 px). |
| `$wgDiscordComponentEmbedDescriptionBudget` | `200` | Visible characters of description, card and `og:description` alike. |
| `$wgDiscordComponentEmbedThumbnailWidth` | `1200` | Thumbnail width in pixels. |
| `$wgDiscordComponentEmbedNamespaces` | `null` | Namespace IDs that get previews; `null` is all of them. |
| `$wgDiscordComponentEmbedOpenGraph` | `true` | Add `og:*` tags for keys nothing else on the wiki emits. |
| `$wgDiscordComponentEmbedFooter` | `true` | Print the site name under the divider. |
| `$wgDiscordComponentEmbedFooterEmoji` | `''` | Emoji before the site name, written as `<:name:id>`. |

<<<<<<< Updated upstream
=======
## Licence

Released into the public domain under [The Unlicense](COPYING).
