# Changelog

## [1.1.1] - 2026-06-09

### Changed
- Renamed CSS/JS asset files from `m4w-cookie-consent.*` to `m4w-cc-core.*` to avoid ad blocker detection
- Version bumped from 1.1.0 to 1.1.1

## [1.1.0] - 2026-06-02

### Added
- Configurable cookie ID via admin settings (`cookie_id` field)
- Header Scripts setting — inject custom JavaScript into `<head>` without `<script>` tags
- WordPress i18n support: `Text Domain: m4w-cookie-consent` with `load_muplugin_textdomain()`
- `get_default_text()` and `get_default_category_text()` methods using `__()` for translatable default strings
- Slovak translation files (`.po`/`.mo`)
- Legacy `cookieyes-consent` cookie detection in JS via `_m4wCC.oldCookie`

### Changed
- **Breaking:** Text fields (`banner_title`, `banner_description`, buttons, categories) are now single strings instead of `sk`/`en` arrays. Overrides are optional; empty fields fall back to the translated default via `__()`.
- Admin UI: per-language inputs replaced with single input + placeholder showing the default translated text
- Sanitization updated for new string-based text fields
- JS `getCookie()` now escapes regex special chars and uses `decodeURIComponent`
- JS `setCookie()` now uses `encodeURIComponent` for safe cookie values
- Version bumped from 1.0.0 to 1.1.0

### Fixed
- Plugin description typo ("Wodrpess" → "WordPress")

### Removed
- Bilingual (SK/EN) per-language text arrays from settings
- `get_locale()` method — locale-based logic replaced by WordPress i18n
