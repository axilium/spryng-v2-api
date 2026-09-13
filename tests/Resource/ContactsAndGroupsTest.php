<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Tests\Resource;

use Acme\SpryngMessaging\SpryngClient;
use Acme\SpryngMessaging\Tests\Double\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ContactsAndGroupsTest extends TestCase
{
    public function testItCreatesAContactAndFillsInTheAccountReference(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['id' => 'c-1']], 201);

        $contact = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->contacts()->create([
            'firstName' => 'Ada',
            'lastName'  => 'Lovelace',
            'addresses' => [['addressType' => 'MSISDN', 'addressValue' => '+31612345678']],
        ]);

        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/contacts', $transport->lastPath());
        self::assertSame([
            'accountReference' => 'SPNL0000000',
            'firstName'        => 'Ada',
            'lastName'         => 'Lovelace',
            'addresses'        => [['addressType' => 'MSISDN', 'addressValue' => '+31612345678']],
        ], $transport->decodedRequestBody());
        self::assertSame(['id' => 'c-1'], $contact);
    }

    public function testItLeavesAnExplicitAccountReferenceAlone(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []], 201);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))
            ->contacts()
            ->create(['accountReference' => 'SPNL9999999', 'firstName' => 'Ada']);

        self::assertSame('SPNL9999999', $transport->decodedRequestBody()['accountReference']);
    }

    public function testItBulkCreatesAndBulkUpserts(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []]);
        $contacts  = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->contacts();

        $contacts->createMany([['firstName' => 'Ada'], ['firstName' => 'Grace']]);
        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/contacts/bulk', $transport->lastPath());
        self::assertSame([
            'accountReference' => 'SPNL0000000',
            'contacts'         => [['firstName' => 'Ada'], ['firstName' => 'Grace']],
        ], $transport->decodedRequestBody());

        // The prose says PUT, the sample says PATCH; see docs/api/CONFLICTS.md.
        $contacts->createOrUpdateMany([['firstName' => 'Ada']]);
        self::assertSame('PATCH', $transport->lastMethod);
        self::assertSame('/v2/contacts/bulk', $transport->lastPath());
    }

    public function testItUpdatesGetsListsAndDeletesContacts(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['contacts' => [['id' => 'c-1']]]]);
        $contacts  = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->contacts();

        $contacts->update('c-1', ['firstName' => 'Ada']);
        self::assertSame('PUT', $transport->lastMethod);
        self::assertSame('/v2/contacts/c-1', $transport->lastPath());
        self::assertSame(['firstName' => 'Ada'], $transport->decodedRequestBody());

        $contacts->get('c-1');
        self::assertSame('/v2/contacts/c-1', $transport->lastPath());

        $list = $contacts->list(['search' => 'Ada', 'sortOrder' => 'asc']);
        self::assertSame('/v2/contacts/filter?search=Ada&sortOrder=asc', $transport->lastPath());
        self::assertSame([['id' => 'c-1']], $list->all());

        $contacts->delete(['c-1', 'c-2']);
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/contacts?contactId=c-1&contactId=c-2', $transport->lastPath());

        $contacts->delete('c-3');
        self::assertSame('/v2/contacts?contactId=c-3', $transport->lastPath());
    }

    public function testItManagesGroups(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['groups' => []]]);
        $groups    = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->groups();

        $groups->create('Night shift', 'People on call');
        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('/v2/groups', $transport->lastPath());
        self::assertSame([
            'accountReference' => 'SPNL0000000',
            'name'             => 'Night shift',
            'description'      => 'People on call',
        ], $transport->decodedRequestBody());

        $groups->create('No description');
        self::assertArrayNotHasKey('description', $transport->decodedRequestBody());

        $groups->update('g-1', ['name' => 'Renamed']);
        self::assertSame('PUT', $transport->lastMethod);
        self::assertSame('/v2/groups/g-1', $transport->lastPath());

        $groups->list(['name' => 'Night']);
        self::assertSame('/v2/groups?name=Night', $transport->lastPath());

        $groups->get('g-1');
        self::assertSame('/v2/groups/g-1', $transport->lastPath());

        $groups->delete('g-1');
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/groups?groupId=g-1', $transport->lastPath());
    }

    public function testItManagesGroupMembership(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['contacts' => []]]);
        $groups    = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->groups();

        $groups->addContacts('g-1', ['c-1', 'c-2']);
        self::assertSame('PUT', $transport->lastMethod);
        self::assertSame('/v2/groups/g-1/contacts', $transport->lastPath());
        self::assertSame(['contactIds' => ['c-1', 'c-2']], $transport->decodedRequestBody());

        $groups->addContacts('g-1', 'c-3');
        self::assertSame(['contactIds' => ['c-3']], $transport->decodedRequestBody());

        $groups->removeContacts('g-1', ['c-1']);
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertSame('/v2/groups/g-1/contacts?contactId=c-1', $transport->lastPath());

        $groups->contacts('g-1', ['optOutStatus' => 'OptedIn']);
        self::assertSame('GET', $transport->lastMethod);
        self::assertSame('/v2/groups/g-1/contacts?optOutStatus=OptedIn', $transport->lastPath());
    }
}
