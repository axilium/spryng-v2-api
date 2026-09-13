<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Tests\Resource;

use Acme\SpryngMessaging\Dto\Message;
use Acme\SpryngMessaging\Dto\Recipient;
use Acme\SpryngMessaging\Enum\MessageType;
use Acme\SpryngMessaging\SpryngClient;
use Acme\SpryngMessaging\Tests\Double\FakeTransport;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class SchedulingTest extends TestCase
{
    public function testItSplitsAMessageIntoScheduleDispatchAndRecipients(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['scheduleId' => 's-1']], 201);
        $sendTime  = new DateTimeImmutable('2026-09-01 08:30:00', new DateTimeZone('UTC'));

        $schedule = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->schedules()->create(
            dispatch: new Message(
                recipients:  [new Recipient('+31612345678')],
                text:        'Your shift starts in an hour.',
                from:        'Acme',
                messageType: MessageType::NotificationsAndReminders,
                name:        'shift-reminder',
            ),
            sendTime: $sendTime,
        );

        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/schedules/delayed', $transport->lastPath());
        self::assertSame([
            'scheduleInformation' => ['sendTime' => '2026-09-01T08:30:00.000Z'],
            'dispatch'            => [
                'channel'     => 'SMS',
                'body'        => ['text' => 'Your shift starts in an hour.'],
                'from'        => 'Acme',
                'name'        => 'shift-reminder',
                'messageType' => 'NotificationsAndReminders',
            ],
            'recipients'          => [['msisdn' => '+31612345678']],
        ], $transport->decodedRequestBody());
        self::assertSame(['scheduleId' => 's-1'], $schedule);
    }

    public function testItCarriesContactsAndGroupsNextToTheDispatch(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []], 201);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->schedules()->create(
            dispatch:   new Message(contactIds: ['c-1'], text: 'Hello'),
            sendTime:   new DateTimeImmutable('2026-09-01 08:30:00', new DateTimeZone('UTC')),
            groupIds:   ['g-1'],
            scheduleInformation: ['frequency' => 'Weekly', 'repeatTimes' => 3],
        );

        $body = $transport->decodedRequestBody();

        self::assertSame(
            ['sendTime' => '2026-09-01T08:30:00.000Z', 'frequency' => 'Weekly', 'repeatTimes' => 3],
            $body['scheduleInformation']
        );
        self::assertSame([['contactId' => 'c-1']], $body['contacts']);
        self::assertSame([['groupId' => 'g-1']], $body['groups']);
        self::assertArrayNotHasKey('addressBook', $body['dispatch']);
        self::assertArrayNotHasKey('accountReference', $body['dispatch']);
    }

    public function testItReadsUpdatesAndDeletesSchedules(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['schedules' => []]]);
        $schedules = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->schedules();

        $schedules->get('s-1');
        self::assertSame('/v2/schedules/delayed/s-1', $transport->lastPath());

        $schedules->list(['StatusFilter' => 'Pending']);
        self::assertSame('/v2/schedules/delayed?StatusFilter=Pending', $transport->lastPath());

        $schedules->reschedule('s-1', new DateTimeImmutable('2026-09-02 09:00:00', new DateTimeZone('UTC')));
        self::assertSame('PATCH', $transport->lastMethod);
        self::assertSame('/v2/schedules/delayed/s-1', $transport->lastPath());
        self::assertSame(
            ['scheduleInformation' => ['sendTime' => '2026-09-02T09:00:00.000Z']],
            $transport->decodedRequestBody()
        );

        $schedules->update('s-1', ['dispatch' => ['name' => 'Renamed']]);
        self::assertSame(['dispatch' => ['name' => 'Renamed']], $transport->decodedRequestBody());

        $schedules->delete('s-1');
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/schedules/delayed/s-1', $transport->lastPath());
    }

    public function testItCreatesAThrottleSchedule(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['throttleScheduleId' => 't-1']], 201);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->throttleSchedules()->create(
            dispatch: Message::text('+31612345678', 'Hello', from: 'Acme'),
            throttleScheduleInformation: [
                'startDate'     => '2026-09-01',
                'endDate'       => '2026-09-30',
                'timeSlotStart' => '09:00',
                'timeSlotEnd'   => '17:00',
                'rateOfSend'    => 100,
                'daysOfWeek'    => ['Monday', 'Tuesday'],
            ],
        );

        $body = $transport->decodedRequestBody();

        self::assertSame('/v2/throttle-schedule', $transport->lastPath());
        self::assertSame(100, $body['throttleScheduleInformation']['rateOfSend']);
        self::assertSame([['msisdn' => '+31612345678']], $body['recipients']);
        self::assertSame(['text' => 'Hello'], $body['dispatch']['body']);
    }

    public function testItDrivesARunningThrottleSchedule(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['recipients' => []]]);
        $throttle  = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->throttleSchedules();

        $throttle->pause('t-1');
        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/throttle-schedule/t-1/pause', $transport->lastPath());

        $throttle->resume('t-1');
        self::assertSame('/v2/throttle-schedule/t-1/resume', $transport->lastPath());

        $throttle->unsent('t-1');
        self::assertSame('GET', $transport->lastMethod);
        self::assertSame('/v2/throttle-schedule/t-1/unsent', $transport->lastPath());

        $throttle->addRecipients('t-1', ['+31612345678'], ['c-1']);
        self::assertSame('/v2/throttle-schedule/t-1/recipients', $transport->lastPath());
        self::assertSame([
            'recipients' => [['msisdn' => '+31612345678']],
            'contacts'   => [['contactId' => 'c-1']],
        ], $transport->decodedRequestBody());

        $throttle->update('t-1', ['throttleScheduleInformation' => ['rateOfSend' => 50]]);
        self::assertSame('PATCH', $transport->lastMethod);

        $throttle->list(['Status' => 'Running']);
        self::assertSame('/v2/throttle-schedule?Status=Running', $transport->lastPath());

        $throttle->get('t-1');
        self::assertSame('/v2/throttle-schedule/t-1', $transport->lastPath());

        $throttle->delete('t-1');
        self::assertSame('DELETE', $transport->lastMethod);
    }
}
