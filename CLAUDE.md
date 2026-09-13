# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this is

A standalone, reusable Composer package that wraps the Spryng Messaging v2 API
for outbound SMS. It is consumed by several unrelated PHP projects, so it must
stay framework-agnostic.

## Hard constraints

These are deliberate design decisions, not oversights. Do not "improve" them
without being asked:

1. **Zero runtime dependencies.** No Guzzle, no PSR-18, no Symfony components,
   no polyfills. Only `ext-curl` and `ext-json`. If something needs an HTTP
   client, it goes behind the `Transport` interface.
2. **PHP 8.1 as the floor.** Enums, `readonly` properties and named arguments
   are fine. Do not use 8.2+ features such as `readonly` classes, DNF types or
   `#[\Override]`.
3. **Scope is client plus DTOs.** No Symfony bundle, no Laravel service
   provider, no delivery-report webhook parser, no CLI commands. If one of
   those is wanted it becomes a separate package.
4. **No `version` key in `composer.json`.** Versions come from git tags only.

## Layout

```
src/
  SpryngClient.php        Public entry point; auth, URLs, error mapping, resources
  Resource/AbstractResource.php  Base class: unwraps "data", fills in accountReference
  Resource/*.php          One class per portal section, 61 endpoints in total
  Http/Transport.php      Interface: method, url, headers, body -> HttpResponse
  Http/CurlTransport.php  Default implementation
  Http/StreamTransport.php  Fallback without ext-curl
  Http/HttpResponse.php   Immutable response value object
  Dto/Message.php         Outbound message, validates in the constructor
  Dto/Recipient.php       E.164 number plus per-recipient variables and metadata
  Dto/SendResult.php      requestId and message ids from the 202
  Dto/Collection.php      A page of rows from a list endpoint
  Enum/                   Channel, CharacterSet, MessageType
  Exception/              Typed hierarchy, all extend SpryngException
tests/
  Double/FakeTransport.php  Records the request, replays a canned response
  EndpointCoverageTest.php  Fails when a documented endpoint has no method
tools/
  fetch-docs.php          Mirrors the developer portal into docs/api
  build-openapi.php       Derives docs/openapi.yaml from that mirror
  lib/                    HTML block parser and a small YAML writer
docs/
  api/                    Generated mirror of developer.spryng.nl
  openapi.yaml            Generated OpenAPI 3.1 description of the v2 API
```

Everything under `docs/api` and `docs/openapi.yaml` is generated. Change the
tools and re-run `fin docs`; never hand-edit the output.

## Development environment

CLI-only Docksal, PHP 8.1: `fin init`, `fin test`, `fin docs`. Docksal is a
convenience; the package itself assumes nothing beyond PHP 8.1 and Composer.

## Conventions

- `declare(strict_types=1);` in every file.
- Everything `final` unless it is explicitly designed for extension.
- Constructor property promotion with `readonly` for value objects.
- Named arguments in examples and tests; the `Message` constructor has enough
  parameters that positional calls get unreadable.
- Code, comments, docblocks and commit messages in English. The package is
  public.
- Validation that can be done locally is done locally, in the DTO constructor,
  throwing `InvalidArgumentException`. Failures that only the API can detect
  throw a `SpryngException` subclass.

## Testing

```bash
composer install
composer test
```

Tests must never make a real HTTP request. Inject `FakeTransport` into
`SpryngClient`. When you add a behaviour, add a test that pins the exact
request that goes out (URL, headers, JSON body) or the exact exception type
that comes back.

## Backwards compatibility

Consumers pin on `^1.0`. Within 1.x:

- Public `readonly` properties on DTOs are part of the API. Renaming one is a
  major version.
- New constructor parameters go last and always have a default.
- New exception types must extend an existing one that callers already catch.

## Before tagging a release

1. `composer validate --strict`
2. `vendor/bin/phpunit`
3. `git archive HEAD | tar -t` and confirm `docs/`, `tools/`, `tests/` and
   `.docksal/` are absent; consumers get the library, not the workshop
4. Update `CHANGELOG.md`
5. Bump `SpryngClient::VERSION` to match the tag (it goes into the User-Agent)
6. `git tag -a vX.Y.Z -m "X.Y.Z" && git push origin vX.Y.Z`

## Response shapes

Request payloads are typed. Responses are not: list endpoints return a
`Collection` of decoded arrays, single resources return an array. That is
deliberate. The portal documents response bodies by example rather than by
schema, so readonly properties would pin down fields that are only as reliable
as one sample payload. Do not "improve" this into a DTO per resource without
being asked.

## Open items

- Only the authentication path has been exercised against the live API: an
  invalid key returns the 401 envelope this package models. Request bodies and
  response payloads still rest on the documentation alone. Run
  `tools/smoke-test.php` with real credentials to close that gap; it is
  read-only unless you pass `--send`. Credentials live in `.env`, which is
  git-ignored, never in `.docksal/docksal.env`.
- `docs/api/CONFLICTS.md` lists the places where the portal contradicts itself.
  Where prose and cURL sample disagree, this package follows the sample.
