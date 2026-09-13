# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-09-13

### Added

- `Webhooks::subscriptions()`, which lists every subscription on the account
  through `GET /webhooks/subscriptions`. The portal does not document that
  endpoint, so `EndpointCoverageTest` never asked for it.

## [1.0.0] - 2026-09-13

First release. Written against the Spryng Messaging v2 API as documented on
<https://developer.spryng.nl>.

### Added

- `SpryngClient`, authenticating with `X-Api-Key` against
  `https://api.spryng.nl/v2`, with a resource per section of the portal:
  `messages()`, `contacts()`, `groups()`, `templates()`, `schedules()`,
  `throttleSchedules()`, `webhooks()`, `optOuts()` and `balance()`. All 61
  documented endpoints are covered.
- `Message` and `Recipient` DTOs that validate locally: text or template but not
  both, recipients or contact ids but not both, E.164 numbers, a validity inside
  the documented 72 hour window. `Recipient::parse()` normalises the number
  formats that turn up in practice.
- `Channel`, `CharacterSet` and `MessageType` enums.
- `SendResult` for the 202 the send endpoint returns, and `Collection` for the
  paged list endpoints.
- Typed exceptions under `ApiException`: `ValidationException` (400),
  `AuthenticationException` (401/403), `NotFoundException` (404),
  `ConflictException` (409) and `RateLimitException` (429), each carrying the
  parsed error envelope. `TransportException` covers requests that never
  arrived.
- `CurlTransport` (default) and `StreamTransport` (no ext-curl).
- `tools/fetch-docs.php`, which mirrors the developer portal into `docs/api/`
  through the portal's own content API, and `tools/build-openapi.php`, which
  reconstructs `docs/openapi.yaml` and a `docs/api/CONFLICTS.md` report of
  everything in the documentation that contradicts itself.
- A CLI-only Docksal environment (`fin init`, `fin test`, `fin docs`) pinned to
  PHP 8.1.
- `EndpointCoverageTest`, which fails when the portal documents an endpoint the
  client does not implement.
- `tools/smoke-test.php`, which holds the client against the live API with real
  credentials from a git-ignored `.env`. Read-only unless `--send` is passed.
  See `.env.example`.
- `SpryngClient::MSGPIT_BASE_URL`, for pointing the client at a local
  [msgpit](https://github.com/raymondsteffann/msgpit) instance that catches
  messages instead of delivering them.
- `SpryngClient::fromEnvironment()`, which reads `SPRYNG_API_KEY`,
  `SPRYNG_ACCOUNT_REFERENCE` and `SPRYNG_BASE_URL`. The constructor falls back
  to `SPRYNG_BASE_URL` when no base URL is passed.

### Notes

- Development files (`docs/`, `tools/`, `tests/`, `.docksal/`) are excluded from
  the release archive through `.gitattributes` and `composer.json`.
- The pre-release draft of this package targeted `rest.spryngsms.com` with a
  bearer token and a v1-shaped payload. None of that was ever tagged, and none
  of it survives; `docs/API-NOTES.md` records what changed.
