# Authoring previews

Every page has a link preview without any markup. This document describes what that
preview contains and how to override it with `{{#discord_embed: ...}}`.

## The automatic preview

With nothing written on the page at all, the preview is:

- **Title** — the page title, including its namespace prefix on pages outside the
  main namespace.
- **Description** — the article's intro: *every* paragraph before the first heading,
  not just the first one, and a lead written as a list counts too. Text a reader
  cannot see is left out: infoboxes, navboxes, hatnotes, collapsed sections,
  reference markers, figure captions, tables, images and anything hidden with
  `display:none`, `visibility:hidden`, `hidden` or `aria-hidden`.
- **Image** — the article's image as chosen by
  [PageImages](https://www.mediawiki.org/wiki/Extension:PageImages), rendered at
  1200px wide. That is the same file PageImages uses for its own `og:image`, so the
  preview and the wiki agree on the picture. Without PageImages, or on a page with no
  image, the preview is text only.

The description is capped at 300 characters by default
(`$wgDiscordComponentEmbedDescriptionBudget`). When it is cut, it is cut at a word
boundary and ends with an ellipsis, so a long intro never ends mid-word.

## Overriding it

```wikitext
{{#discord_embed:
 | title = Doorman
 | description = A game about doors.
 | image = File:Doorman cover.png
 | image-description = Cover art
 | gallery1 = File:Room 1.png
 | gallery1-description = The first room
 | gallery2 = File:Room 2.png
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
argument nobody recognises is treated as a mistake: nothing is published for that
page and the reason is logged (see *When something is wrong* below). A leading pipe
after the colon is fine, so the argument-per-line style above is the intended one.

| argument | meaning |
| --- | --- |
| `title` | Preview title. |
| `description` | Preview description, in place of the article's intro. |
| `image` | A `File:` title used as the section thumbnail. |
| `image-description` | Caption for `image`. |
| `galleryN` | A `File:` title, `N` counting from 1. |
| `galleryN-description` | Caption for `galleryN`. |
| `spoilerN` | `yes` marks gallery item `N` as a spoiler. |
| `buttonN` | One button, `N` counting from 1. |
| `accent` | Container accent colour, e.g. `#5865F2`. |
| `spoiler` | `yes` marks the whole preview as a spoiler. |
| `mode` | `merge` (default) or `replace`. |
| `enabled` | `no` switches this page's preview off entirely. |

Boolean arguments accept `yes`, `true`, `1`, `on` and `no`, `false`, `0`, `off`.

Numbered arguments must run from 1 upwards without gaps: `gallery2` without
`gallery1` is a mistake rather than a way of numbering from two.

## Images and galleries

`image`, `galleryN` and `image-description` take **titles of files uploaded to the
wiki**. `File:`, `Image:` and the bare file name are all accepted
(`File:Cover.png`, `Cover.png`), as is a bracketed `[[File:Cover.png]]`.

A file that does not exist, or that is not media, is dropped from the preview: the
rest of the preview is still published, and the reason is logged. If an authored
`image` does not resolve, the preview does not quietly fall back to the article's own
image; use `mode = replace` if you want only your own values.

At most 10 gallery items are accepted.

## Buttons

A button target is either a wikitext page link or a full URL, and the label is
derived from it:

| written | label | lands on |
| --- | --- | --- |
| `[[Doorman]]` | `Doorman` | the page |
| `[[Doorman\|Read more]]` | `Read more` | the page |
| `[[Doorman#Abilities]]` | `Doorman` | the page, at the section |
| `[[#Abilities]]` | `#Abilities` | the page the call appears on, at the section |
| `[[/Subpage]]` | `Subpage` | the subpage, on a page whose namespace allows them |
| `https://example.com/read` | `read` | the URL |

Inside a page link the pipe is free, because MediaWiki protects it. After a plain
URL, write `{{!}}` for the label separator, since a bare `|` separates the parser
function's own arguments.

Links resolve exactly the way MediaWiki resolves them, including fragments and
subpage-relative forms, so a button lands where a reader clicking the same wikitext
would. Interwiki links resolve to the foreign wiki.

Three things are deliberately dropped, each without affecting the other buttons:

- **category links** — `[[Category:Gaming]]` categorises rather than links;
- **file links** — `[[File:Cover.png]]` embeds an image;
- **targets MediaWiki itself renders as plain text** — for example `[[../Sibling]]`
  in a namespace without subpages, or bare words that are not a link at all.

A button pointing at a page that does not exist yet points at the page, not at the
editor: a preview whose button opens an edit form is worse than one that opens an
empty page.

**Limits.** At most 25 buttons (five per action row, five rows), each label at most
80 characters and each URL at most 512. A label you write yourself that is too long
is a mistake, because silently shortening your words is worse; a label derived from a
long title is simply cut to fit.

## Several calls on one page

Every call on a page contributes to the same preview, so a template can supply the
buttons while the article sets the accent:

```wikitext
{{#discord_embed: accent = #5865F2 | button1 = [[Doorman]] }}
{{#discord_embed: title = Set here, and it wins }}
```

Merging happens per key: a later call wins on the arguments it names, and arguments
it does not name keep their earlier value. Writing a target replaces that whole
entry, so `button1 = <new url>` drops the label an earlier `button1` had. An argument
written with an empty value is ignored rather than an eraser; use `mode = replace` to
drop what a template supplied.

Modules take part in the same merge (see below), and wikitext takes precedence over
them: an argument you write with `{{#discord_embed:}}` wins a clash with a module
whatever order the two ran in.

However many calls a page makes, the result is exactly one preview.

## From Lua

With [Scribunto](https://www.mediawiki.org/wiki/Extension:Scribunto) installed, a
module reaches the same arguments through `mw.ext.discordembed`, so anything a module
can compute — a title out of a data table, a gallery assembled in a loop — can go
into the preview:

```lua
local p = {}

function p.hero()
	mw.ext.discordembed.embed{
		title = 'Abrams',
		description = 'A hard-boiled bruiser who starts fights.',
		image = 'File:Deadlock.jpg',
		['image-description'] = 'Deadlock cover art',
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

Argument names are the ones in the table above, hyphens included. Values are strings,
numbers are converted (`button1 = 42` becomes `"42"`), `true` and `false` mean `yes`
and `no` — so `enabled = false` switches the preview off the way it does in
wikitext — and `nil` is the same as not naming the argument. An unknown argument
rejects the page's preview and is logged, exactly as it is for a template.

## Merge and replace

- `mode = merge` (the default) fills every gap from the article: an omitted title,
  description or image falls back to the automatic value.
- `mode = replace` uses only what you wrote. A page with only buttons gets a preview
  with only buttons.

## Settings for a whole wiki

See the table in [the README](../README.md). Sites that want previews only in some
namespaces set `$wgDiscordComponentEmbedNamespaces` to the namespace IDs, for
example `[ NS_MAIN, NS_TALK ]`.

**Components or Discord's own card.** The payload is what carries the buttons, the
gallery and the accent, and Discord renders its title as body-sized bold text. Set
`$wgDiscordComponentEmbedComponents = false` to publish only the Open Graph tags:
Discord then draws its own card, with the larger clickable title — the same shape
Wikipedia's links get — at the cost of everything a component can hold. The
thumbnail still arrives through `og:image`.

**Header size.** Inside a component the title's size is Discord's, not the wiki's.
The one lever is markdown: `$wgDiscordComponentEmbedTitleHeading` puts a heading in
front of the clickable title, `1` being the largest and `3` the closest to body
text. `0`, the default, leaves it as plain bold.

## When something is wrong

An unusable configuration publishes **no** payload and leaves the page's normal
rendering and its existing Open Graph tags alone. It does not half-publish, and it
does not fail the page.

The reason is written to the `DiscordComponentEmbed` log channel at warning level,
including which argument was the problem:

```
Discord preview configuration rejected: Unknown argument "buttom1" passed to {{#discord_embed:}}.
```

To see it, point the log group at a file (or your usual log sink):

```php
$wgDebugLogGroups['DiscordComponentEmbed'] = "/var/log/mediawiki/discordembed.log";
```

Missing files and dropped buttons are logged at debug level on the same channel,
because leaving something out on purpose is not an error.
