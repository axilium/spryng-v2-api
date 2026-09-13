<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Enum;

/**
 * Traffic classification. Purely for reporting: it groups messages in the
 * portal and in the message history export, and does not change routing.
 */
enum MessageType: string
{
    case MarketingAndResearch     = 'MarketingAndResearch';
    case NotificationsAndReminders = 'NotificationsAndReminders';
    case AuthenticationAndSecurity = 'AuthenticationAndSecurity';
    case PaymentsAndCollections   = 'PaymentsAndCollections';
    case InternalOperations       = 'InternalOperations';
    case CustomerSupport          = 'CustomerSupport';
    case Conversational           = 'Conversational';
}
