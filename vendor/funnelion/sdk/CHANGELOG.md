# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Funnelion\Cookie\GoogleAnalytics` — reads the visitor's GA4 client id (`_ga` cookie) and session id (per-stream `_ga_<stream>` cookie, both the current `GS2` and legacy `GS1` value formats).
- Optional `gaClientId` / `gaSessionId` fields on `Funnelion\Resolve\Request` and `Funnelion\FormEvent\Request`, sent as `ga_client_id` / `ga_session_id`. Funnelion fires its GA4 events for this visitor's inbound calls and emails under these identifiers, which is what makes GA4 attribute them to the session — and therefore the campaign — the visitor is actually in. Without them such events report under GA4's "Unassigned" channel group. See README "Google Analytics 4 attribution".

## [0.3.0] — 2026-05-18

### Added

- Optional `language` field on `Funnelion\Resolve\Request` and `Funnelion\FormEvent\Request`. Free-form string ("en", "lt", "de-DE", …) that Funnelion stores on the visitor's tracking session and uses to drive downstream attribution (notably for the planned Funnelion-fires-GA4 dispatch of inbound email and call events). See README "Language" section for the recommended detection patterns.

## [0.2.0] — 2026-05-18

### Added

- `Funnelion\Client::formEvent()` / `formEventOrNull()` — record a form-submission event against the visitor's tracking session. Mirrors the new `POST /api/v1/form-event` endpoint.
- `Funnelion\FormEvent\Request` and `Funnelion\FormEvent\Response` value objects.

### Changed

- `Client` internals refactored to share POST + auth + JSON decoding between `resolve()` and `formEvent()`. No behavioural change for existing callers.

## [0.1.0] — 2026-05-18

Initial public release.

### Added

- `Funnelion\Client` with `resolve()` (throws typed exceptions) and `resolveOrNull()` (the "must always work" pattern).
- `Funnelion\Config` value object: `siteToken`, `baseUri`, `timeoutSeconds`, `userAgent`.
- `Funnelion\Resolve\Request`, `Response`, `SwapZone` value objects mirroring the public `POST /api/v1/resolve` shape.
- `Funnelion\Html\ZoneSwapper` — replaces `data-funnelion="<Zone Name>"` markers in HTML with resolved addresses; rewrites `tel:` and `mailto:` hrefs on `<a>` elements.
- `Funnelion\Cookie\Session` — builds Set-Cookie header values and reads from `$_COOKIE`.
- `Funnelion\Http\Transport` interface, `CurlTransport` default, `MockTransport` for tests.
- Typed exception hierarchy (`FunnelionException` base + `TimeoutException`, `NetworkException`, `AuthenticationException`, `ValidationException`, `RateLimitException`, `ServerException`).
- Docker-based dev workflow (no PHP required on the host).
