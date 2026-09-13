# Spryng API documentation mirror

Portal content revision: `20260619-1` (68 pages).

A local copy of <https://developer.spryng.nl>, fetched from the portal
content API by `tools/fetch-docs.php`. `openapi.yaml` in the parent
directory is derived from the `raw/` payloads by `tools/build-openapi.php`.

Everything here is generated. Re-run the fetcher instead of editing.

## Set up

- [Introduction](home/introduction.md) - `home/introduction`
- [Getting started](home/getting-started.md) - `home/getting-started`

## Messaging

- [Send](messaging/v2-messages-create.md) - `messaging/v2-messages-create`
- [Inbox](messaging/v2-messages-get-inbox.md) - `messaging/v2-messages-get-inbox`
- [Get by messageId](messaging/v2-messages-get-by-id.md) - `messaging/v2-messages-get-by-id`
- [Get all](messaging/v2-messages-get-all.md) - `messaging/v2-messages-get-all`
- [Get all by requestId](messaging/v2-requests-get-messages-from-request.md) - `messaging/v2-requests-get-messages-from-request`
- [Get by requestId](messaging/v2-requests-get-by-id.md) - `messaging/v2-requests-get-by-id`
- [Get all requests](messaging/v2-requests-get-all.md) - `messaging/v2-requests-get-all`
- [Get URL metrics](messaging/v2-shortener-get.md) - `messaging/v2-shortener-get`
- [Get all URL click metrics](messaging/v2-shortener-get-by-request-id.md) - `messaging/v2-shortener-get-by-request-id`

## Contacts

- [Create](contacts/v2-contacts-create.md) - `contacts/v2-contacts-create`
- [Bulk create](contacts/v2-contacts-bulk-create.md) - `contacts/v2-contacts-bulk-create`
- [Update](contacts/v2-contacts-update.md) - `contacts/v2-contacts-update`
- [Bulk update/create](contacts/v2-contacts-bulk-create-or-update.md) - `contacts/v2-contacts-bulk-create-or-update`
- [Delete](contacts/v2-contacts-delete.md) - `contacts/v2-contacts-delete`
- [Get](contacts/v2-contacts-get-by-id.md) - `contacts/v2-contacts-get-by-id`
- [Get all](contacts/v2-contacts-get-all.md) - `contacts/v2-contacts-get-all`

## Groups

- [Create](groups/v2-groups-create.md) - `groups/v2-groups-create`
- [Get](groups/v2-groups-get-by-id.md) - `groups/v2-groups-get-by-id`
- [Get all](groups/v2-groups-get-all.md) - `groups/v2-groups-get-all`
- [Update](groups/v2-groups-update.md) - `groups/v2-groups-update`
- [Delete](groups/v2-groups-delete.md) - `groups/v2-groups-delete`
- [Add contacts](groups/v2-groups-add-contacts-to-group.md) - `groups/v2-groups-add-contacts-to-group`
- [Remove contacts](groups/v2-groups-delete-contacts-from-group.md) - `groups/v2-groups-delete-contacts-from-group`
- [Get contacts from Group](groups/v2-groups-get-contacts-from-group.md) - `groups/v2-groups-get-contacts-from-group`

## Schedule

- [Create](schedule/v2-scheduled-messages-create.md) - `schedule/v2-scheduled-messages-create`
- [Delete](schedule/v2-scheduled-messages-delete.md) - `schedule/v2-scheduled-messages-delete`
- [Update](schedule/v2-scheduled-messages-update.md) - `schedule/v2-scheduled-messages-update`
- [Get](schedule/v2-scheduled-messages-get.md) - `schedule/v2-scheduled-messages-get`
- [Get all](schedule/v2-scheduled-messages-get-all.md) - `schedule/v2-scheduled-messages-get-all`

## Throttling

- [Create](throttling/v2-scheduled-throttle-create.md) - `throttling/v2-scheduled-throttle-create`
- [Get](throttling/v2-scheduled-throttle-get.md) - `throttling/v2-scheduled-throttle-get`
- [Get all](throttling/v2-scheduled-throttle-get-all.md) - `throttling/v2-scheduled-throttle-get-all`
- [Get unsent messages](throttling/v2-scheduled-throttle-get-unsent.md) - `throttling/v2-scheduled-throttle-get-unsent`
- [Edit](throttling/v2-scheduled-throttle-update.md) - `throttling/v2-scheduled-throttle-update`
- [Delete](throttling/v2-scheduled-throttle-delete.md) - `throttling/v2-scheduled-throttle-delete`
- [Pause](throttling/v2-scheduled-throttle-pause.md) - `throttling/v2-scheduled-throttle-pause`
- [Resume](throttling/v2-scheduled-throttle-resume.md) - `throttling/v2-scheduled-throttle-resume`
- [Add recipients](throttling/v2-scheduled-throttle-add-recipients.md) - `throttling/v2-scheduled-throttle-add-recipients`

## Webhooks

- [Create](webhooks/v2-webhooks-create-subscription.md) - `webhooks/v2-webhooks-create-subscription`
- [Update URL](webhooks/v2-webhooks-update-urls-for-event.md) - `webhooks/v2-webhooks-update-urls-for-event`
- [Delete event](webhooks/v2-webhooks-delete-event-subscription.md) - `webhooks/v2-webhooks-delete-event-subscription`
- [Delete all](webhooks/v2-webhooks-delete-subscription.md) - `webhooks/v2-webhooks-delete-subscription`
- [Get webhooks for an account](webhooks/v2-webhooks-get-subscriptions.md) - `webhooks/v2-webhooks-get-subscriptions`
- [Get all events](webhooks/v2-webhooks-get-events.md) - `webhooks/v2-webhooks-get-events`
- [Authentication](webhooks/v2-webhooks-update-authentication.md) - `webhooks/v2-webhooks-update-authentication`

## Opt outs

- [Create](opt-outs/v2-optouts-create.md) - `opt-outs/v2-optouts-create`
- [Delete](opt-outs/v2-optouts-delete.md) - `opt-outs/v2-optouts-delete`
- [Get all](opt-outs/v2-optouts-get-all.md) - `opt-outs/v2-optouts-get-all`

## Templates

- [Create](templates/v2-templates-create.md) - `templates/v2-templates-create`
- [GET](templates/v2-templates-get-by-id.md) - `templates/v2-templates-get-by-id`
- [GET all](templates/v2-templates-get-all.md) - `templates/v2-templates-get-all`
- [Update](templates/v2-templates-update.md) - `templates/v2-templates-update`
- [Delete](templates/v2-templates-delete.md) - `templates/v2-templates-delete`
- [Lock](templates/v2-templates-lock.md) - `templates/v2-templates-lock`
- [Unlock](templates/v2-templates-unlock.md) - `templates/v2-templates-unlock`
- [Change tags](templates/v2-templates-update-tags.md) - `templates/v2-templates-update-tags`
- [Copy](templates/v2-templates-copy.md) - `templates/v2-templates-copy`

## Balance

- [Balance alert](balance/v2-balance-alerts.md) - `balance/v2-balance-alerts`
- [Get](balance/v2-balance.md) - `balance/v2-balance`
- [Share](balance/v2-wallet-delegate.md) - `balance/v2-wallet-delegate`
- [Get Movement](balance/v2-wallet-delegate-movement.md) - `balance/v2-wallet-delegate-movement`

## SMPP

- [Introduction](smpp/v1-smpp-introduction.md) - `smpp/v1-smpp-introduction`
- [Set up](smpp/v1-smpp-set-up.md) - `smpp/v1-smpp-set-up`
- [Sending a message](smpp/v1-smpp-send-message.md) - `smpp/v1-smpp-send-message`
- [Message states & error codes](smpp/v1-smpp-message-states.md) - `smpp/v1-smpp-message-states`

## Email to SMS

- [Getting started](email-to-sms/getting-started.md) - `email-to-sms/getting-started`
