# Usage

Every endpoint the Spryng developer portal documents, grouped the way the portal
groups them. `docs/openapi.yaml` is the generated description this is built
against; `docs/api/` is the mirrored documentation itself.

## Creating a client

```php
use Axilium\SpryngV2\SpryngClient;

$client = new SpryngClient(
    apiKey:           getenv('SPRYNG_API_KEY'),
    accountReference: 'SPNL0000000',
);
```

| Argument | Purpose |
|---|---|
| `apiKey` | Created in the portal under Developer Tools. Sent as `X-Api-Key`. |
| `accountReference` | Your account, e.g. `SPNL0000000`. Optional here, but most read endpoints require it as a header and the send endpoint wants it in the body, so setting it once saves repeating it. |
| `transport` | Defaults to `CurlTransport`. Swap in `StreamTransport` without ext-curl, or a `FakeTransport` in tests. |
| `baseUrl` | Defaults to `https://api.spryng.nl/v2`. |

The resources hang off the client and are reused between calls:

```php
$client->messages();
$client->contacts();
$client->groups();
$client->templates();
$client->schedules();
$client->throttleSchedules();
$client->webhooks();
$client->optOuts();
$client->balance();
```

## Messages

### Sending

`Message::text()` covers the common case and parses the numbers for you:

```php
use Axilium\SpryngV2\Dto\Message;

$result = $client->messages()->send(Message::text(
    ['+31612345678', '0031687654321'],
    'Your shift tomorrow is confirmed.',
    from: 'Acme',
));

$result->requestId;      // one per send() call
$result->messageIds;     // one per recipient
$result->messageId();    // the first one, for a single recipient
$result->count();
```

The full constructor exposes everything the endpoint takes:

```php
use Axilium\SpryngV2\Dto\Recipient;
use Axilium\SpryngV2\Enum\CharacterSet;
use Axilium\SpryngV2\Enum\MessageType;

$message = new Message(
    recipients:   [new Recipient('+31612345678', variables: ['name' => 'Ada'])],
    text:         'Hi [name], your shift starts at 09:00.',
    from:         'Acme',
    characterSet: CharacterSet::Auto,
    messageType:  MessageType::NotificationsAndReminders,
    name:         'shift-reminders-2026-08-22',
    validity:     new DateTimeImmutable('+4 hours'),
    metaData:     ['run' => 'nightly'],
);
```

- `text` and `templateId` are mutually exclusive; exactly one is required.
- `recipients` and `contactIds` are mutually exclusive; exactly one is required.
- `[name]` placeholders in the body are filled per recipient from that
  recipient's `variables`.
- `validity` is the retry window: more than a minute and at most 72 hours out.
- `name` and `metaData` come back in the message history export, which is what
  makes a run findable afterwards.

Sending to the address book instead of to numbers:

```php
$client->messages()->send(new Message(
    contactIds: ['486c5ddf-e047-49bf-a5ed-b3e3555f8843'],
    templateId: 'a1b2c3d4-0000-0000-0000-000000000000',
    from:       'Acme',
));
```

Shortening a URL, which produces a per-recipient link and click metrics:

```php
Message::text('+31612345678', 'Your invoice: [shorten: https://example.com/very/long/url]');
```

### Numbers

`Recipient` insists on E.164 with the leading plus. `Recipient::parse()` accepts
the shapes that turn up in a database and normalises them:

```php
Recipient::parse('+31 6-1234 5678');            // +31612345678
Recipient::parse('0031612345678');              // +31612345678
Recipient::parse('31612345678');                // +31612345678
Recipient::parse('0612345678', defaultCountryCode: '31');
```

A national number without a country code throws, because there is nothing to
prefix it with. Anything malformed throws `InvalidArgumentException` before a
request goes out.

### Reading

```php
$client->messages()->get($messageId);
$client->messages()->list(['status' => 'Delivered', 'pageSize' => 50]);
$client->messages()->inbox(['pageNo' => 1]);

$client->messages()->getRequest($requestId);
$client->messages()->listRequests(['Status' => 'Completed']);
$client->messages()->listRequestMessages($requestId);

$client->messages()->urlVisits();
$client->messages()->urlVisitsForRequest($requestId);
```

Filters are passed as an array and go into the query string untouched, so the
names are the ones the portal documents. Watch the capitalisation: `/messages`
documents `pageSize` while `/requests` documents `PageSize`. Each method's
docblock lists the filters for that endpoint.

Booleans, enums and `DateTimeInterface` values are converted on the way out, and
an array filter is repeated per value (`?contactId=a&contactId=b`):

```php
$client->messages()->list([
    'retrieveMessageBody' => true,
    'startTime'           => new DateTimeImmutable('-1 day'),
]);
```

## Collections

Every list endpoint returns a `Collection`: countable, iterable, and carrying
the paging fields.

```php
$page = $client->messages()->list(['pageSize' => 50, 'pageNo' => 2]);

$page->totalCount;
$page->currentPage;
$page->isEmpty();
$page->first();
$page->all();

foreach ($page as $message) {
    echo $message['messageId'], ' ', $message['status'], PHP_EOL;
}
```

Rows are plain arrays. The portal documents response bodies by example rather
than by schema, so typing them into properties would promise a stability the
documentation does not offer. `$page->raw` holds the undecorated response.

## Contacts

```php
$contact = $client->contacts()->create([
    'firstName' => 'Ada',
    'lastName'  => 'Lovelace',
    'quickName' => 'ADA1',
    'addresses' => [[
        'addressType'  => 'MSISDN',
        'addressValue' => '+31612345678',
        'optOutStatus' => 'OptedIn',
    ]],
    'contactMetadata' => ['employeeId' => '8842'],
]);

$client->contacts()->get($contact['id']);
$client->contacts()->update($contact['id'], ['firstName' => 'Augusta']);
$client->contacts()->list(['search' => 'Lovelace', 'sortOrder' => 'asc']);
$client->contacts()->delete([$contact['id']]);

$client->contacts()->createMany([$first, $second]);
$client->contacts()->createOrUpdateMany([$first, $second]);
```

`accountReference` is filled in from the client when the payload does not carry
one.

## Groups

```php
$group = $client->groups()->create('Night shift', 'People on call');

$client->groups()->addContacts($group['id'], [$contactId]);
$client->groups()->contacts($group['id'], ['optOutStatus' => 'OptedIn']);
$client->groups()->removeContacts($group['id'], [$contactId]);

$client->groups()->update($group['id'], ['name' => 'Night shift (EU)']);
$client->groups()->list(['name' => 'Night']);
$client->groups()->delete($group['id']);
```

Removing a contact from a group does not delete the contact, and deleting a
group does not delete its members.

## Templates

```php
use Axilium\SpryngV2\Enum\CharacterSet;
use Axilium\SpryngV2\Enum\MessageType;

$template = $client->templates()->create(
    name:         'Shift reminder',
    content:      'Hi [name], your shift starts at [time].',
    messageType:  MessageType::NotificationsAndReminders,
    characterSet: CharacterSet::Auto,
);

$client->templates()->update($template['id'], ['content' => 'New body']);
$client->templates()->lock($template['id']);
$client->templates()->unlock($template['id']);
$client->templates()->updateTags($template['id'], [$tagId]);
$client->templates()->copy($template['id'], 'SPNL9999999');
$client->templates()->list(['Search' => 'shift']);
$client->templates()->delete($template['id']);
```

Send one by passing its id as a `Message`'s `templateId`.

## Schedules

A delayed send is a dispatch plus a moment. Build the dispatch as a `Message`;
the destinations are lifted out of it and sent alongside, which is the shape the
endpoint wants.

```php
$schedule = $client->schedules()->create(
    dispatch: Message::text('+31612345678', 'Your shift starts in an hour.', from: 'Acme'),
    sendTime: new DateTimeImmutable('2026-09-01 08:30:00'),
);

$client->schedules()->reschedule($schedule['id'], new DateTimeImmutable('+1 day'));
$client->schedules()->update($schedule['id'], ['dispatch' => ['name' => 'Renamed']]);
$client->schedules()->list(['StatusFilter' => 'Pending']);
$client->schedules()->get($schedule['id']);
$client->schedules()->delete($schedule['id']);
```

Repeating sends, and sending to contacts or whole groups:

```php
$client->schedules()->create(
    dispatch:   Message::text([], 'Weekly summary', from: 'Acme'),
    sendTime:   new DateTimeImmutable('next monday 09:00'),
    groupIds:   [$groupId],
    scheduleInformation: ['frequency' => 'Weekly', 'repeatTimes' => 12],
);
```

## Throttle schedules

For a bulk run that should not land all at once, or should stay inside office
hours:

```php
$run = $client->throttleSchedules()->create(
    dispatch: Message::text($numbers, 'Our opening hours have changed.', from: 'Acme'),
    throttleScheduleInformation: [
        'startDate'     => '2026-09-01',
        'endDate'       => '2026-09-30',
        'timeSlotStart' => '09:00',
        'timeSlotEnd'   => '17:00',
        'rateOfSend'    => 100,
        'daysOfWeek'    => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
    ],
);

$client->throttleSchedules()->pause($run['id']);
$client->throttleSchedules()->unsent($run['id']);
$client->throttleSchedules()->addRecipients($run['id'], ['+31612345678']);
$client->throttleSchedules()->resume($run['id']);
$client->throttleSchedules()->update($run['id'], [
    'throttleScheduleInformation' => ['rateOfSend' => 50],
]);
```

## Webhooks

```php
$client->webhooks()->events();

$client->webhooks()->subscribeUrl(
    eventType:              'MessageDelivered',
    url:                    'https://example.com/hooks/spryng',
    requiresAuthentication: true,
    contactEmail:           'ops@example.com',
);

$client->webhooks()->subscriptions();                 // every event type at once
$client->webhooks()->subscriptionsFor('MessageDelivered');
$client->webhooks()->updateUrls('MessageDelivered', ['https://example.com/hooks/v2']);
$client->webhooks()->updateAuthentication([
    'basic' => ['username' => 'spryng', 'password' => $secret],
]);

$client->webhooks()->unsubscribe('MessageDelivered');
$client->webhooks()->unsubscribeAll();
```

Receiving and verifying the payloads is out of scope for this package, on
purpose: parsing them is framework work, and this stays framework-agnostic.

## Opt-outs

```php
$client->optOuts()->create('+31612345678');
$client->optOuts()->list(['SortOrder' => 'desc']);
$client->optOuts()->delete('+31612345678');
```

Spryng blocks opted-out numbers itself. Only remove an opt-out with the consent
of the recipient.

## Balance

```php
$balance = $client->balance()->get();

// The API returns the amounts as strings, e.g. "115.82000"
$available = (float) $balance['available'];

$client->balance()->createAlert(
    threshold:      100.0,
    contactChannel: 'Email',
    contactAddress: 'ops@example.com',
    alertType:      'LowBalance',
);

$client->balance()->delegate(['SPNL1111111' => 25.0]);
$client->balance()->delegationMovements();
```

Prepay accounts only.

## Anything the resources do not cover

The client is usable directly, with the same authentication, URL building and
error mapping:

```php
$client->get('/messages', ['pageSize' => 10]);
$client->post('/messages', $payload);
$client->put($path, $payload);
$client->patch($path, $payload);
$client->delete($path, $query);
```

Each returns the decoded response body.

## Errors

```php
use Axilium\SpryngV2\Exception\ApiException;
use Axilium\SpryngV2\Exception\RateLimitException;
use Axilium\SpryngV2\Exception\TransportException;
use Axilium\SpryngV2\Exception\ValidationException;

try {
    $client->messages()->send($message);
} catch (ValidationException $e) {
    $logger->error($e->getMessage(), ['code' => $e->getErrorCode()]);
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter());
} catch (TransportException $e) {
    // Never arrived. Safe to retry.
} catch (ApiException $e) {
    if ($e->isRetryable()) {
        // 5xx.
    }
}
```

| Status | Exception |
|---|---|
| 400 | `ValidationException` |
| 401, 403 | `AuthenticationException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 429 | `RateLimitException` |
| anything else | `ApiException` |

All of them extend `ApiException`, which extends `SpryngException`.
`TransportException` extends `SpryngException` directly: nothing reached the
API, so there is no status to report.

Mistakes that can be caught without a request - a malformed number, a message
with neither text nor template - throw `InvalidArgumentException` from the DTO
constructor instead.

### Retrying

```php
$attempt = 0;

do {
    try {
        return $client->messages()->send($message);
    } catch (RateLimitException $e) {
        sleep($e->getRetryAfter());
    } catch (TransportException $e) {
        sleep(2 ** $attempt);
    } catch (ApiException $e) {
        if (!$e->isRetryable()) {
            throw $e;
        }
        sleep(2 ** $attempt);
    }
} while (++$attempt < 3);
```

Retrying a send is safe in the sense that a rejected or never-delivered request
created nothing. A request that returned 202 did create messages, so retry that
one only if you know it failed.

## Transports

```php
use Axilium\SpryngV2\Http\CurlTransport;
use Axilium\SpryngV2\Http\StreamTransport;

new SpryngClient($key, $account, new CurlTransport(timeout: 30, connectTimeout: 10));
new SpryngClient($key, $account, new StreamTransport(timeout: 30));
```

`StreamTransport` needs `allow_url_fopen`. Implement `Transport` yourself to
route requests through an existing HTTP client, add logging, or return canned
responses.

## Testing against this package

`FakeTransport` records the outgoing request and replays a canned response:

```php
use Axilium\SpryngV2\Tests\Double\FakeTransport;

$transport = FakeTransport::respondingWithJson([
    'data' => ['requestId' => 'r-1', 'messageIds' => ['m-1']],
], 202);

$client = new SpryngClient('test-key', 'SPNL0000000', $transport);
$client->messages()->send(Message::text('+31612345678', 'Hello', from: 'Acme'));

$transport->lastMethod;             // POST
$transport->lastPath();             // /v2/messages
$transport->decodedRequestBody();   // the exact payload that went out
```

It ships in `tests/`, which is excluded from the release archive. Copy it into
your own test suite, or point Composer at the source with `--prefer-source`.

## Framework integration

There is no bundle and no service provider on purpose. Registering the client is
a few lines wherever your container lives.

Symfony:

```yaml
services:
    Axilium\SpryngV2\SpryngClient:
        arguments:
            $apiKey: '%env(SPRYNG_API_KEY)%'
            $accountReference: '%env(SPRYNG_ACCOUNT_REFERENCE)%'
```

Laravel:

```php
$this->app->singleton(SpryngClient::class, static fn (): SpryngClient => new SpryngClient(
    config('services.spryng.key'),
    config('services.spryng.account'),
));
```
