# Test doubles

The unit suite runs with no MediaWiki installed, so anything it needs from outside
the extension is a hand-written fake that lives here.

These fakes replace the extension's own seams, not core classes:

- `FakeLinkResolver` stands in for `MediaWikiLinkResolver`. Resolving a wikitext link
  needs real `Title` behaviour — fragments, subpage resolution, interwiki prefixes —
  and a stub cannot model that faithfully, so the interesting cases are asserted in
  the integration suite against the href MediaWiki itself renders.
- `FakeImageResolver` stands in for the file repository.
- `FakePageImageProvider` stands in for PageImages.

A fake can only confirm the belief that produced it, which is exactly why none of the
core-facing behaviour is tested through one.
