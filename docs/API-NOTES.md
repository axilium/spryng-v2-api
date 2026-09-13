# Notes on the Spryng API

What the API looks like, where that knowledge comes from, and how this package
maps onto it.

Everything below is taken from the documentation mirror in `docs/api/`, which is
pulled straight from the developer portal, and from `docs/openapi.yaml`, which is
derived from it. Where the portal contradicts itself, `docs/api/CONFLICTS.md`
lists the contradiction rather than hiding it.

## How the package maps onto it

`SpryngClient` speaks this API directly: `https://api.spryng.nl/v2`, an
`X-Api-Key` header, an `AccountReference` header on reads and an
`accountReference` field in the bodies that want one. Each portal section has a
resource (`messages()`, `contacts()`, `groups()`, `templates()`, `schedules()`,
`throttleSchedules()`, `webhooks()`, `optOuts()`, `balance()`), and
`tests/EndpointCoverageTest.php` fails if the portal grows an endpoint that none
of them covers.

Request payloads are typed where the API is stable enough to type: `Message`,
`Recipient`, the enums. Responses come back as decoded arrays inside a
`Collection` or a small result object, because the portal documents response
bodies by example rather than by schema, and pinning those examples into
readonly properties would promise a stability that is not on offer.

The v1-shaped API this package started with is gone. If you are upgrading from
that draft: `originator` became `from`, `encoding` became `characterSet`,
`route` is gone, recipients are objects rather than strings and keep their
leading plus, and `send()` moved to `$client->messages()->send()`.

## What has been verified against the live API

### Reads, with real credentials (2026-09-13)

`tools/smoke-test.php` passed every call it makes: `GET /balance`, `/messages`,
`/requests`, `/templates`, `/contacts/filter`, `/groups`, `/optouts` and
`/webhooks/events`. Every response sat under `data`, which is what
`AbstractResource` unwraps.

- `/messages` and `/requests` returned rows. `/templates`, `/contacts/filter`,
  `/groups` and `/optouts` returned none on that account, so the call is
  confirmed but the row shape is not.
- `/balance` returns its amounts as strings, not numbers:
  `{"data":{"available":"115.82000","reserved":"0"}}`. Cast before comparing.
- `/webhooks/events` lists six event ids without the `sms-` prefix the portal
  uses: `message-delivered`, `message-failed`, `message-received`,
  `inbound-opted-out`, `message-updated` and `schedule-updated`. Subscribe with
  those.
- `GET /webhooks/subscriptions` is not in the portal documentation but exists.
  It returns the account's subscriptions under `data.events`, each with
  `eventType`, `callbacks` and `requiresAuthentication`.

Nothing that writes has run against the live API: sending, contacts, templates,
schedules, and webhook subscriptions and authentication all rest on the
documentation, plus msgpit for sending and webhooks.

### Authentication (2026-08-22)

A request with a deliberately invalid key returned:

```
HTTP 401
{"errors":[{"errorCode":"UnauthenticatedError",
            "errorMessage":"Request for authenticated route '/v2/balance' was unauthenticated"}]}
```

The same 401 comes back from `/v2/messages`, `/v2/requests`, `/v2/templates`,
`/v2/contacts/filter`, `/v2/groups`, `/v2/optouts` and `/v2/webhooks/events`.

That confirms the host, that those are all real authenticated routes rather than
404s, and that the error envelope has exactly the shape modelled in `ApiError`.
It says nothing about request bodies or response payloads; the reads above
cover part of that.

## Base URL

```
https://api.spryng.nl
```

Served over HTTPS only. Every resource in this package lives under `/v2`, so
`https://api.spryng.nl/v2` is the useful base. The version is part of the path;
Spryng bumps it for breaking changes.

## Authentication

An API key is created in the Spryng portal under Developer Tools, by a user with
both Admin and Developer status. Keys can carry an expiry date and be revoked
individually. It travels as a header:

```
X-Api-Key: <key>
```

There is no bearer token and no OAuth. Note that the request header table on
several portal pages shows `Api-Key` in its example while the parameter table
above it says `X-Api-Key`; the parameter table is the one to follow.

Most `GET` endpoints additionally require the account to be named in a header.
Two pages spell that header `AcccountReference`, with three c's - the inbox and
the URL shortener. It is a typo in the documentation rather than a different
header; this package sends `AccountReference` everywhere.

The header is:

```
AccountReference: SPNL0000000
```

On `POST /v2/messages` the same value goes in the request body as
`accountReference` instead. It is not a secret in the way the key is, but the
portal asks you to treat it as one.

## Sending a message

```
POST https://api.spryng.nl/v2/messages
X-Api-Key: <key>
Content-Type: application/json
```

```json
{
    "accountReference": "SPNL0000000",
    "channel": "SMS",
    "from": "Acme",
    "body": {
        "text": "Your shift tomorrow is confirmed."
    },
    "recipients": [
        {
            "msisdn": "+31612345678",
            "variables": {},
            "metaData": {}
        }
    ]
}
```

Required: `accountReference`, `channel`, `body`, and one of `body.text` or
`body.templateId`. `from` is required unless a default sender is configured on
the account. Either `recipients` or `addressBook` must be present, never both.

Optional: `name` (a label that shows up in the message history export),
`characterSet`, `validity`, `messageType`, and a `metaData` map that comes back
on export.

Notable limits and behaviours:

- `recipients` holds 1 to 50.000 entries; `addressBook.contacts` caps at 10.000
  contact ids per request.
- `validity` is an absolute timestamp, at least a minute and at most 72 hours
  out. It defaults to 72 hours.
- `characterSet` is `Auto`, `GSM` or `Unicode`. GSM fits roughly 140 characters
  per part, Unicode roughly 70. `Auto` picks GSM unless the text needs Unicode.
- Embedding `[shorten: http://example.com/long]` in `body.text` produces a
  per-recipient short link. Not available on trial accounts.
- Variables in the body use square brackets and are filled per recipient from
  `recipients[].variables`.

The success response is `202 Accepted`, not `200`:

```json
{
    "data": {
        "requestId": "8dcad127-bb9c-48fe-9827-db2fe57d53fb",
        "messageIds": ["0929df71-d1ea-4b02-87b2-9789b332a92e"]
    }
}
```

The request is accepted, not delivered. Delivery state comes from
`GET /v2/messages/{messageId}`, from `GET /v2/requests/{requestId}/messages`, or
from a webhook.

## Recipient number format

E.164, including the leading plus: `+31612345678`, `+447948751633`. No spaces,
no national prefix, no leading zero after the country code.

## Responses and errors

Successful payloads are wrapped in `data`. Collections add `totalCount` and
`currentPage` next to the array.

Errors use a consistent envelope:

```json
{
    "errors": [
        {
            "errorCode": "unauthenticatedError",
            "errorMessage": "Missing or invalid credentials"
        }
    ]
}
```

`errorCode` is a stable machine-readable string; `errorMessage` is prose meant
for a log, not for an end user. Some endpoints add fields to the envelope
(`title`, `status`, `internalErrors`), so parse it leniently.

Status codes that appear across the documented endpoints:

| Status | Meaning |
|---|---|
| 200 | Success |
| 201 | Created |
| 202 | Accepted, used by the send endpoint |
| 204 | Success with no body, used by most deletes |
| 400 | Malformed request or a failed validation |
| 401 | Missing, expired or revoked API key |
| 403 | Authenticated, but not allowed on this account |
| 404 | Unknown resource |
| 409 | Conflict, e.g. a duplicate contact |

`429` is not documented per endpoint but the portal describes it under rate
limiting. `422` does not occur; validation failures come back as `400`.

## Rate limiting

One default limit applies across all public endpoints, and the portal does not
publish the number. On `429` the documented remedy is to wait five seconds
before retrying. Spryng can raise the limit on request. No `X-RateLimit-*`
headers are documented, so a client should not count on them being there.

## What is not in this package

SMPP and email-to-SMS. Both are documented in the portal and mirrored in
`docs/api/`, and neither is a REST endpoint, so neither fits behind this client.

Receiving webhook payloads is also out of scope. This package subscribes and
unsubscribes through `/v2/webhooks/subscriptions`; parsing what Spryng then
posts to your endpoint is framework work, and the package stays
framework-agnostic. See the hard constraints in `CLAUDE.md`.
