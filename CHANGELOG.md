# Changelog

## v1.1.0 - 2025-09-30
- feat: added REST endpoint `fox-rate/v1/rate` with a 5-minute local cache, 2 s upstream timeout, and stale fallback (valid for up to 24 h).
- feat: return `Cache-Control: no-store` and `Pragma: no-cache` headers on endpoint responses.

## v1.0.2
- Initial release.
