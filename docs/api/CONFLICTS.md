# Documentation conflicts and gaps

Produced by `tools/build-openapi.php` while deriving `docs/openapi.yaml` (37 paths).

Each entry is a place where the portal contradicts itself or leaves something
out. Verify against the live API before relying on the generated schema there.

Linting the specification surfaces a handful more. Those are almost all
placeholder values in Spryng's own samples - `"messageType": "messageType"`,
`"validity": "validity"` - which cannot satisfy the enum or format the same
page documents. They are left in place: the sample is what the portal shows.

- contacts/v2-contacts-bulk-create-or-update: method differs - prose says PUT, cURL sample says PATCH. Used the sample.
- contacts/v2-contacts-update: path differs - prose says `/contacts/{contactsId}`, cURL sample says `/contacts/{contactId}`. Used the sample.
- contacts/v2-contacts-update: `accountReference` is documented as required but the cURL sample leaves it out.
- email-to-sms/getting-started: no endpoint documented, left out of the spec.
- groups/v2-groups-add-contacts-to-group: `contactsId` is documented as required but the cURL sample leaves it out.
- groups/v2-groups-delete: path differs - prose says `/groups/{groupId}`, cURL sample says `/groups`. Used the sample.
- home/getting-started: no endpoint documented, left out of the spec.
- home/introduction: no endpoint documented, left out of the spec.
- smpp/v1-smpp-introduction: no endpoint documented, left out of the spec.
- smpp/v1-smpp-message-states: no endpoint documented, left out of the spec.
- smpp/v1-smpp-send-message: no endpoint documented, left out of the spec.
- smpp/v1-smpp-set-up: no endpoint documented, left out of the spec.
- webhooks/v2-webhooks-delete-event-subscription: path differs - prose says `/webhooks/events/{eventId}`, cURL sample says `/webhooks/events/{eventType}`. Used the sample.
