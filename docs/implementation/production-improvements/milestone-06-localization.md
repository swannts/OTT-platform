# Milestone 06 Make Bengali browsing preserve English fallback

## Existing implementation and gaps

Blog titles, body, categories, and output tags already have Bengali fields and fallback. The tag query searched only English values.

## Changes

Added Bengali JSON tag matching and localized a clone of the article for presentation. Tests cover the Bengali tag result, fallback title, English tags, and published-only behavior.

## Files, database, API, and compatibility

`apps/api/app/Http/Controllers/API/BlogApiController.php`, `apps/api/tests/Feature/BlogLocalizationTest.php`. Existing English substring matching, API fields, and persisted originals are preserved.

## Verification and measurements

PHP syntax passed. Laravel feature execution, browser language switching, and database checks are pending.

## Remaining work

Verify Bengali search and tags on MySQL, then audit labels, SEO metadata, content search, and admin translation editing. Cover locale persistence and warm-cache switching in a real browser.

## Rollback

Revert the localization commits together. No database rollback is needed.

## Commit references

`d275fc0`, `3c04099`
