<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Resource;

use Acme\SpryngMessaging\Exception\SpryngException;

/**
 * Prepaid balance, low-balance alerts and moving credit to sub-accounts.
 *
 * Only meaningful on a prepay account.
 *
 * @see https://developer.spryng.nl/balance/v2-balance
 */
final class Balance extends AbstractResource
{
    /**
     * The balance of the account this client authenticates as.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(): array
    {
        return $this->data($this->client->get('/balance'));
    }

    /**
     * Configures the alert that fires when the balance drops below a threshold.
     *
     * @param float  $threshold      The balance to alert on.
     * @param string $contactChannel Where the alert goes, e.g. Email.
     * @param string $contactAddress The address on that channel.
     * @param string $alertType      Documented by example only.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function createAlert(
        float $threshold,
        string $contactChannel,
        string $contactAddress,
        string $alertType,
        bool $enabled = true,
    ): array {
        return $this->data($this->client->post('/balance-alerts', [
            'alertType'      => $alertType,
            'threshold'      => $threshold,
            'enabled'        => $enabled,
            'contactChannel' => $contactChannel,
            'contactAddress' => $contactAddress,
        ]));
    }

    /**
     * Moves credit from this account to sub-accounts.
     *
     * @param array<string, float> $amountsBySubAccount Account reference => amount.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delegate(array $amountsBySubAccount): array
    {
        $delegations = [];

        foreach ($amountsBySubAccount as $subAccountReference => $amount) {
            $delegations[] = [
                'subAccountReference' => (string) $subAccountReference,
                'amount'              => $amount,
            ];
        }

        return $this->data($this->client->post('/wallet/delegate', ['delegations' => $delegations]));
    }

    /**
     * The history of credit moved between accounts.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delegationMovements(): array
    {
        return $this->data($this->client->get('/wallet/delegate'));
    }
}
