# Spryng Messaging

Zero-dependency PHP client for the [Spryng Messaging v2 API](https://developer.spryng.nl/messaging/v2-messages-create).

- Covers all 61 documented v2 endpoints: messages, contacts, groups,
  templates, schedules, throttling, webhooks, opt-outs and balance.
- No Composer dependencies. Only `ext-curl` and `ext-json`.
- PHP 8.1+, typed DTOs, enums, `readonly` properties.
- Local validation so malformed payloads never cost an API call.
- Typed exceptions that tell you whether a retry makes sense.
- Swappable transport, so tests run without touching the network.

## Installation

```bash
composer require acme/spryng-messaging
```

## Quick start

```php
use Acme\SpryngMessaging\SpryngClient;
use Acme\SpryngMessaging\Dto\Message;

$client = new SpryngClient(getenv('SPRYNG_API_KEY'), 'SPNL0000000');

$result = $client->messages()->send(Message::text(
    '+31612345678',
    'Your shift tomorrow is confirmed.',
    from: 'Acme',
));

echo $result->messageId();
```

Both constructor arguments come from the Spryng portal: an API key from
Developer Tools, and the account reference the key belongs to.

The API answers `202 Accepted`. That means queued, not delivered: the result
carries a `requestId` and one message id per recipient, and delivery status is a
separate call or a webhook.

```php
$status = $client->messages()->get($result->messageId());

echo $status['status'];   // Sent, Delivered, Failed, ...
```

Sending to many numbers at once is the same call - one request, one requestId:

```php
$result = $client->messages()->send(Message::text(
    ['+31612345678', '+31687654321'],
    'The office is closed today.',
    from: 'Acme',
));

foreach ($client->messages()->listRequestMessages($result->requestId) as $message) {
    echo $message['msisdn'], ': ', $message['status'], PHP_EOL;
}
```

## The rest of the API

Every section of the portal has a resource on the client:

```php
$client->messages();           // send, read, inbox, requests, URL metrics
$client->contacts();           // the address book, single and bulk
$client->groups();             // groups and their membership
$client->templates();          // reusable bodies, locking, tags, copying
$client->schedules();          // delayed sends
$client->throttleSchedules();  // rate-limited bulk runs
$client->webhooks();           // event subscriptions
$client->optOuts();            // opt-out registry
$client->balance();            // prepaid balance, alerts, wallet delegation
```

See [docs/USAGE.md](docs/USAGE.md) for each of them.

## Error handling

```php
use Acme\SpryngMessaging\Exception\ApiException;
use Acme\SpryngMessaging\Exception\AuthenticationException;
use Acme\SpryngMessaging\Exception\ConflictException;
use Acme\SpryngMessaging\Exception\NotFoundException;
use Acme\SpryngMessaging\Exception\RateLimitException;
use Acme\SpryngMessaging\Exception\TransportException;
use Acme\SpryngMessaging\Exception\ValidationException;

try {
    $client->messages()->send($message);
} catch (ValidationException $e) {
    // 400, the request was rejected. Do not retry it unchanged.
    $logger->error($e->getMessage(), ['code' => $e->getErrorCode()]);
} catch (AuthenticationException $e) {
    // 401/403, fix the key or the account reference.
} catch (NotFoundException $e) {
    // 404, unknown id.
} catch (ConflictException $e) {
    // 409, it already exists.
} catch (RateLimitException $e) {
    // 429, wait $e->getRetryAfter() seconds.
} catch (TransportException $e) {
    // The request never arrived: DNS, TLS, timeout. Safe to retry.
} catch (ApiException $e) {
    // Anything else. $e->isRetryable() is true for 5xx.
}
```

Everything above extends `SpryngException`; everything the API itself returned
extends `ApiException`, which carries the parsed error envelope:

```php
$e->getStatusCode();   // 400
$e->getErrorCode();    // "invalid_msisdn", the first error code
$e->getErrors();       // list<ApiError>, each with ->code and ->message
$e->getBody();         // the full decoded response
```

## Documentation

- [docs/USAGE.md](docs/USAGE.md) - full API of the package, framework integration, retry patterns
- [docs/PUBLISHING.md](docs/PUBLISHING.md) - how to release and publish this package on Packagist
- [docs/API-NOTES.md](docs/API-NOTES.md) - how the Spryng v2 API works, and where this package still disagrees with it
- [docs/openapi.yaml](docs/openapi.yaml) - OpenAPI 3.1 description of all 61 documented endpoints
- [docs/api/](docs/api/) - a local mirror of <https://developer.spryng.nl>
- [docs/api/CONFLICTS.md](docs/api/CONFLICTS.md) - places where the portal contradicts itself

Spryng does not publish a machine-readable specification, and the portal is a
single-page app that fetches its content from an undocumented content API. Both
the mirror and the OpenAPI document are generated from that content API by the
scripts in `tools/`; see [Working on this package](#working-on-this-package).

None of it ships to consumers. `docs/`, `tools/`, `tests/` and `.docksal/` are
marked `export-ignore` in `.gitattributes` and excluded in `composer.json`, so
they stay in the repository but out of the release archive.

## Requirements

| | |
|---|---|
| PHP | 8.1 or higher |
| Extensions | curl, json |
| Dependencies | none |

## Working on this package

Development runs in a CLI-only [Docksal](https://docksal.io) environment: PHP
8.1 - the supported floor - and Composer, no web server and no database.

```bash
fin init          # start the container and install dependencies
fin test          # run the test suite
fin docs          # refresh docs/api and rebuild docs/openapi.yaml
```

To check the package against a newer runtime, point the stack at another image:

```bash
CLI_IMAGE=docksal/cli:php8.3-3.8 fin project restart && fin test
```

Docksal is a convenience, not a requirement. Any PHP 8.1+ with Composer works:

```bash
composer install
composer test
php tools/fetch-docs.php
php tools/build-openapi.php
```

Tests use `FakeTransport` and never hit the network.

### Regenerating the documentation

`tools/fetch-docs.php` reads the portal content API - three unauthenticated
endpoints that need only an `X-Correlation-Id` header holding a UUID - and
writes both the raw payloads and a readable markdown mirror to `docs/api/`.
`tools/build-openapi.php` then reconstructs `docs/openapi.yaml` from those
payloads: paths and methods from the cURL samples, parameters from the tables,
schemas inferred from the tables and example bodies.

Both are generated. Re-run `fin docs` after Spryng ships a new portal revision
instead of editing the output, and read the diff on `docs/api/CONFLICTS.md`:
that file is where the generator reports everything it could not reconcile. A
new endpoint there also fails `EndpointCoverageTest`, which is the point.

The generated specification lints clean with
`docker run --rm -v "$PWD":/spec redocly/cli lint docs/openapi.yaml`, which
`fin docs` runs for you when Docker is available. The remaining warnings are
placeholder values in Spryng's own samples.

### Checking against the live API

The test suite proves the package sends what the documentation describes; it
cannot prove the documentation is right. `tools/smoke-test.php` does:

```bash
cp .env.example .env     # then fill in your key and account reference
fin exec php tools/smoke-test.php
```

Credentials come from `.env`, which is git-ignored. Note that `fin exec` does
not pass host environment variables into the container, so prefixing the command
with `SPRYNG_API_KEY=...` works outside Docksal but not through `fin`; the file
works in both.

The smoke test only reads: balance, messages, requests, templates, contacts,
groups, opt-outs and webhook events. Add `--send=+31612345678 --from=Acme`, or
set `SPRYNG_SMOKE_TO` and `SPRYNG_SMOKE_FROM`, to also send one real message.
That costs credits.

## License

MIT. See [LICENSE](LICENSE).
