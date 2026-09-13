<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tools;

use Axilium\SpryngV2\Dto\Message;
use Axilium\SpryngV2\Exception\ApiException;
use Axilium\SpryngV2\Exception\SpryngException;
use Axilium\SpryngV2\Exception\TransportException;
use Axilium\SpryngV2\SpryngClient;
use Throwable;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/lib/DotEnv.php';

/**
 * Holds the client against the live API.
 *
 * The test suite proves the package sends what the documentation describes. It
 * cannot prove the documentation is right. This does: it makes real calls with
 * real credentials and reports what came back.
 *
 * Read-only by default. Sending an actual message costs credits and needs to be
 * asked for explicitly.
 *
 * Credentials come from .env in the project root; copy .env.example to start.
 * A variable already set in the environment wins over the file.
 *
 * Usage:
 *   php tools/smoke-test.php [--send=+31612345678] [--from=Acme]
 */
final class SmokeTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function __construct(private readonly SpryngClient $client)
    {
    }

    public function run(?string $sendTo, ?string $from): int
    {
        $this->line('Reading. None of this sends anything or costs credits.');

        $this->check('GET /balance', fn (): string => $this->describe($this->client->balance()->get()));

        $this->check('GET /messages', function (): string {
            $messages = $this->client->messages()->list(['pageSize' => 1]);

            return sprintf('%d row(s), totalCount %s', count($messages), $messages->totalCount ?? 'absent');
        });

        $this->check('GET /requests', function (): string {
            $requests = $this->client->messages()->listRequests(['PageSize' => 1]);

            return sprintf('%d row(s)', count($requests));
        });

        $this->check('GET /templates', function (): string {
            $templates = $this->client->templates()->list(['PageSize' => 1]);

            return sprintf('%d row(s)', count($templates));
        });

        $this->check('GET /contacts/filter', function (): string {
            return sprintf('%d row(s)', count($this->client->contacts()->list()));
        });

        $this->check('GET /groups', function (): string {
            return sprintf('%d row(s)', count($this->client->groups()->list()));
        });

        $this->check('GET /optouts', function (): string {
            return sprintf('%d row(s)', count($this->client->optOuts()->list()));
        });

        $this->check('GET /webhooks/events', function (): string {
            return sprintf('%d event type(s)', count($this->client->webhooks()->events()));
        });

        if ($sendTo !== null) {
            $this->line('');
            $this->line(sprintf('Sending one message to %s. This costs credits.', $sendTo));

            $this->check('POST /messages', function () use ($sendTo, $from): string {
                $result = $this->client->messages()->send(Message::text(
                    $sendTo,
                    'Smoke test from the spryng-messaging package.',
                    from: $from,
                ));

                return sprintf(
                    'requestId %s, %d message id(s): %s',
                    $result->requestId,
                    $result->count(),
                    implode(', ', $result->messageIds)
                );
            });
        }

        $this->line('');
        $this->line(sprintf('%d passed, %d failed.', $this->passed, $this->failed));

        return $this->failed === 0 ? 0 : 1;
    }

    /**
     * @param callable(): string $call
     */
    private function check(string $label, callable $call): void
    {
        try {
            $this->passed++;
            $this->line(sprintf('  ok    %-24s %s', $label, $call()));
        } catch (ApiException $exception) {
            $this->passed--;
            $this->failed++;
            $this->line(sprintf(
                '  FAIL  %-24s HTTP %d %s',
                $label,
                $exception->getStatusCode(),
                $exception->getMessage()
            ));
        } catch (TransportException $exception) {
            $this->passed--;
            $this->failed++;
            $this->line(sprintf('  FAIL  %-24s never arrived: %s', $label, $exception->getMessage()));
        } catch (SpryngException | Throwable $exception) {
            $this->passed--;
            $this->failed++;
            $this->line(sprintf('  FAIL  %-24s %s', $label, $exception->getMessage()));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function describe(array $data): string
    {
        $parts = [];

        foreach (array_slice($data, 0, 4, true) as $key => $value) {
            $parts[] = $key . '=' . (is_scalar($value) ? var_export($value, true) : gettype($value));
        }

        return $parts === [] ? '(empty payload)' : implode(' ', $parts);
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}

$options = getopt('', ['send::', 'from::', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<TXT
    Holds the client against the live Spryng API.

    Credentials come from .env in the project root, or from the environment,
    which takes precedence:

      SPRYNG_API_KEY             required
      SPRYNG_ACCOUNT_REFERENCE   required, e.g. SPNL0000000
      SPRYNG_BASE_URL            optional, e.g. http://msgpit:8080/spryng/v2
      SPRYNG_SMOKE_TO            optional, same as --send
      SPRYNG_SMOKE_FROM          optional, same as --from

    Start with: cp .env.example .env

    Options:
      --send=+31612345678   also send one real message. Costs credits.
      --from=Acme           sender to use with --send.

    TXT);
    exit(0);
}

DotEnv::load(dirname(__DIR__) . '/.env');

$apiKey  = getenv('SPRYNG_API_KEY') ?: '';
$account = getenv('SPRYNG_ACCOUNT_REFERENCE') ?: '';

if ($apiKey === '' || $account === '') {
    fwrite(STDERR, <<<TXT
    SPRYNG_API_KEY and SPRYNG_ACCOUNT_REFERENCE are not set.

    Copy .env.example to .env and fill it in:

        cp .env.example .env

    See --help for the alternatives.

    TXT);
    exit(2);
}

$sendTo = is_string($options['send'] ?? null) && $options['send'] !== ''
    ? $options['send']
    : (getenv('SPRYNG_SMOKE_TO') ?: null);

$from = is_string($options['from'] ?? null) && $options['from'] !== ''
    ? $options['from']
    : (getenv('SPRYNG_SMOKE_FROM') ?: null);

exit((new SmokeTest(new SpryngClient($apiKey, $account)))->run($sendTo, $from));
