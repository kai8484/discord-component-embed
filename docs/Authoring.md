# Authoring previews

## The automatic preview

With no markup a page gets:

- **Title** — the page title, namespace prefix included.
- **Description** — every paragraph before the first heading, ignoring infoboxes,
  navboxes, hatnotes, tables, images, reference markers and anything hidden.
  Cut at 200 visible characters, at a word boundary, with an ellipsis.
- **Image** — the page image chosen by PageImages, if that extension is installed.

## Overriding it

```wikitext
{{#discord_embed:
 | title = Doorman
 | description = A game about doors.
 | image = File:Doorman cover.png
 | image-description = Cover art
 | gallery1 = File:Room 1.png
 | gallery1-description = The first room
 | spoiler2 = yes
 | button1 = [[Doorman|Read more]]
 | button2 = https://example.com/notes{{!}}Patch notes
 | accent = #5865F2
 | spoiler = no
 | mode = merge
 | enabled = yes
}}
```

The call renders nothing on the page. Argument names are case-sensitive, and an
unrecognised name rejects the whole preview (logged, nothing published).

| argument | meaning |
| --- | --- |
| `title` | Preview title. |
| `description` | Description, in place of the intro. Discord markdown works. |
| `image` | A `File:` title used as the thumbnail. |
| `image-description` | Caption for `image`. |
| `galleryN` | A `File:` title, `N` from 1. |
| `galleryN-description` | Caption for `galleryN`. |
| `spoilerN` | `yes` hides gallery item `N` behind a spoiler. |
| `buttonN` | One button. |
| `accent` | Container colour, exactly six hex digits, `#5865F2` or `5865F2`. |
| `spoiler` | `yes` hides the whole preview. |
| `mode` | `merge` (default) or `replace`. |
| `enabled` | `no` publishes nothing for this page. |

Booleans also accept `true`/`1`/`on` and `false`/`0`/`off`. Numbered arguments must run
from 1 without gaps; a gap is a mistake, not a renumbering.

**Limits.** 10 gallery items; 25 buttons (5 per row, 5 rows), each label ≤ 80
characters and each URL ≤ 512. An over-long *authored* label or an unusable value is a
mistake and rejects the preview; a derived label is simply cut.

## Images and galleries

`image` and `galleryN` take titles of files uploaded to the wiki — `File:X.png`,
`Image:X.png`, `[[File:X.png]]` or bare `X.png` all work. A file that does not resolve
is dropped from the preview and logged; the rest still publishes. `mode = replace` is
how you drop everything a template supplied.

## Buttons

A target is a page link or a full URL:

| written | label | lands on |
| --- | --- | --- |
| `[[Doorman]]` | `Doorman` | the page |
| `[[Doorman\|Read more]]` | `Read more` | the page |
| `[[Doorman#Abilities]]` | `Doorman` | the page, at the section |
| `[[#Abilities]]` | `#Abilities` | this page, at the section |
| `https://example.com/read` | `read` | the URL |

After a plain URL write `{{!}}` for the label separator, because a bare `|` separates
the parser function's arguments. Category links, file links and anything MediaWiki
would render as plain text are dropped, one button at a time.

## Several calls on one page

Calls merge per key: a later call wins on the arguments it names, and arguments it does
not name keep their earlier value. Writing a target replaces that whole entry. An empty
value is ignored rather than an eraser. However many calls a page makes, it gets one
preview.

## From Lua

With Scribunto, a module reaches the same arguments through `mw.ext.discordembed`:

```lua
local p = {}

function p.hero()
	mw.ext.discordembed.embed{
		title = 'Abrams',
		image = 'File:Deadlock.jpg',
		button1 = '[[Deadlock|About the game]]',
		accent = '#B23A2E',
	}

	return ''
end

return p
```

```wikitext
{{#invoke:Hero embed|hero}}
```

Values are strings, numbers are converted, `true`/`false` mean `yes`/`no`, and `nil`
means "not set". Argument names are the same, hyphens included.

**Precedence:** wikitext beats Lua on a clash, whatever order the two ran in; within one
source the later call wins.

## When something is wrong

An unusable configuration publishes **no** payload, leaves the page's own `og:*` tags
alone, and writes the reason to the `DiscordComponentEmbed` log channel at warning
level, naming the argument. It never half-publishes and never fails the page.

## Settings

See the table in [the README](../README.md). Namespaces, for example, are
`$wgDiscordComponentEmbedNamespaces = [ NS_MAIN, NS_TALK ];`.

**Components or Discord's own card.** The payload carries the buttons, gallery and
accent, and Discord renders its title as body-sized bold text. With
`$wgDiscordComponentEmbedComponents = false` only Open Graph tags are published, so
Discord draws its own card — a larger clickable title, at the cost of everything a
component can hold.

**Header size.** Inside a component the title's size is Discord's, not the wiki's;
`$wgDiscordComponentEmbedTitleHeading` puts a heading in front of it, `1` largest and
`3` closest to body text. `0` leaves it plain bold.
