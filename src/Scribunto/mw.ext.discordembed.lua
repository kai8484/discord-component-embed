local discordembed = {}
local php

--- Declare this page's Discord preview from Lua.
---
--- Takes the same named arguments as {{#discord_embed:}}: title, description,
--- image, image-description, galleryN, galleryN-description, spoilerN, buttonN,
--- accent, spoiler, mode and enabled. Values are strings; true and false mean
--- "yes" and "no". Unknown argument names reject the page's preview and are
--- logged, exactly as they are in wikitext.
---
--- A wikitext {{#discord_embed:}} call on the same page merges with this one:
--- arguments this call names fill the gaps, and wikitext wins any argument the
--- two both set.
---
--- @param table arguments
function discordembed.embed( arguments )
	return php.embed( arguments )
end

function discordembed.setupInterface( options )
	-- Boilerplate
	discordembed.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register this library in the "mw" global
	mw = mw or {}
	mw.ext = mw.ext or {}
	mw.ext.discordembed = discordembed

	package.loaded['mw.ext.discordembed'] = discordembed
end

return discordembed
