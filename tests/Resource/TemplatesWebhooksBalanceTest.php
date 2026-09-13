<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tests\Resource;

use Axilium\SpryngV2\Enum\Channel;
use Axilium\SpryngV2\Enum\CharacterSet;
use Axilium\SpryngV2\Enum\MessageType;
use Axilium\SpryngV2\SpryngClient;
use Axilium\SpryngV2\Tests\Double\FakeTransport;
use PHPUnit\Framework\TestCase;

final class TemplatesWebhooksBalanceTest extends TestCase
{
    public function testItCreatesATemplate(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['id' => 't-1']], 201);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->templates()->create(
            name:         'Shift reminder',
            content:      'Your shift starts at [time].',
            messageType:  MessageType::NotificationsAndReminders,
            characterSet: CharacterSet::Auto,
        );

        self::assertSame('/v2/templates', $transport->lastPath());
        self::assertSame([
            'name'            => 'Shift reminder',
            'templateType'    => 'Text',
            'content'         => 'Your shift starts at [time].',
            'messageType'     => 'NotificationsAndReminders',
            'channelSettings' => ['sms' => ['characterSet' => 'Auto']],
        ], $transport->decodedRequestBody());
    }

    public function testItCoversTheTemplateLifecycle(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['templates' => []]]);
        $templates = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->templates();

        $templates->get('t-1');
        self::assertSame('/v2/templates/t-1', $transport->lastPath());

        $templates->list(['Search' => 'shift']);
        self::assertSame('/v2/templates?Search=shift', $transport->lastPath());

        $templates->update('t-1', ['content' => 'New body']);
        self::assertSame('PUT', $transport->lastMethod);

        $templates->lock('t-1');
        self::assertSame('PATCH', $transport->lastMethod);
        self::assertSame('/v2/templates/t-1/lock', $transport->lastPath());

        $templates->unlock('t-1');
        self::assertSame('/v2/templates/t-1/unlock', $transport->lastPath());

        $templates->updateTags('t-1', ['tag-1', 'tag-2']);
        self::assertSame('/v2/templates/t-1/tags', $transport->lastPath());
        self::assertSame(['tagIds' => ['tag-1', 'tag-2']], $transport->decodedRequestBody());

        $templates->copy('t-1', 'SPNL9999999');
        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/templates/t-1/copy', $transport->lastPath());
        self::assertSame(['destination' => 'SPNL9999999'], $transport->decodedRequestBody());

        $templates->delete('t-1');
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/templates/t-1', $transport->lastPath());
    }

    public function testItSubscribesAWebhookAsAList(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []], 201);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->webhooks()->subscribeUrl(
            eventType:              'MessageDelivered',
            url:                    'https://example.com/hooks/spryng',
            requiresAuthentication: true,
            contactEmail:           'ops@example.com',
        );

        self::assertSame('/v2/webhooks/subscriptions', $transport->lastPath());
        self::assertSame([[
            'eventType'              => 'MessageDelivered',
            'callbacks'              => [['url' => 'https://example.com/hooks/spryng']],
            'requiresAuthentication' => true,
            'contactEmail'           => 'ops@example.com',
        ]], $transport->decodedRequestBody());
    }

    public function testItListsEverySubscriptionOnTheAccount(): void
    {
        $subscription = [
            'eventType'              => 'message-delivered',
            'callbacks'              => [['url' => 'https://example.com/hooks/spryng']],
            'requiresAuthentication' => true,
        ];
        $transport = FakeTransport::respondingWithJson(['data' => ['events' => [$subscription]]]);

        $subscriptions = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->webhooks()->subscriptions();

        self::assertSame('GET', $transport->lastMethod);
        self::assertSame('/v2/webhooks/subscriptions', $transport->lastPath());
        self::assertNull($transport->lastBody);
        self::assertSame([$subscription], $subscriptions->all());
    }

    public function testItManagesWebhookSubscriptions(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['events' => []]]);
        $webhooks  = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->webhooks();

        $webhooks->events();
        self::assertSame('/v2/webhooks/events', $transport->lastPath());

        $webhooks->subscriptionsFor('MessageDelivered');
        self::assertSame('/v2/webhooks/events/MessageDelivered', $transport->lastPath());

        $webhooks->updateUrls('MessageDelivered', ['https://example.com/a', ['url' => 'https://example.com/b']]);
        self::assertSame('PUT', $transport->lastMethod);
        self::assertSame(
            [['url' => 'https://example.com/a'], ['url' => 'https://example.com/b']],
            $transport->decodedRequestBody()
        );

        $webhooks->updateAuthentication(['basic' => ['username' => 'spryng', 'password' => 'hunter2']]);
        self::assertSame('/v2/webhooks/authentication-methods', $transport->lastPath());

        $webhooks->unsubscribe('MessageDelivered');
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/webhooks/events/MessageDelivered', $transport->lastPath());

        $webhooks->unsubscribeAll();
        self::assertSame('/v2/webhooks/subscriptions', $transport->lastPath());
    }

    public function testItManagesOptOuts(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['optOuts' => []]]);
        $optOuts   = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->optOuts();

        $optOuts->create('+31612345678');
        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/optouts', $transport->lastPath());
        self::assertSame([
            'accountReference' => 'SPNL0000000',
            'channel'          => 'SMS',
            'value'            => '+31612345678',
        ], $transport->decodedRequestBody());

        $optOuts->list(['SortOrder' => 'desc']);
        self::assertSame('/v2/optouts?SortOrder=desc', $transport->lastPath());

        $optOuts->delete('+31612345678', Channel::Sms);
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/optouts?Value=%2B31612345678&Channel=SMS', $transport->lastPath());
    }

    public function testItReadsBalanceAndDelegatesCredit(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['balance' => 42.5]]);
        $balance   = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->balance();

        self::assertSame(['balance' => 42.5], $balance->get());
        self::assertSame('/v2/balance', $transport->lastPath());

        $balance->createAlert(
            threshold:      100.0,
            contactChannel: 'Email',
            contactAddress: 'ops@example.com',
            alertType:      'LowBalance',
        );
        self::assertSame('/v2/balance-alerts', $transport->lastPath());
        // Checked as a string: an amount must not be flattened to an integer
        // on the way out.
        self::assertSame(
            '{"alertType":"LowBalance","threshold":100.0,"enabled":true,'
            . '"contactChannel":"Email","contactAddress":"ops@example.com"}',
            $transport->lastBody
        );

        $balance->delegate(['SPNL1111111' => 25.0, 'SPNL2222222' => 10.5]);
        self::assertSame('/v2/wallet/delegate', $transport->lastPath());
        self::assertSame(
            '{"delegations":[{"subAccountReference":"SPNL1111111","amount":25.0},'
            . '{"subAccountReference":"SPNL2222222","amount":10.5}]}',
            $transport->lastBody
        );

        $balance->delegationMovements();
        self::assertSame('GET', $transport->lastMethod);
        self::assertSame('/v2/wallet/delegate', $transport->lastPath());
    }
}
