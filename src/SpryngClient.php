<?php

declare(strict_types=1);

namespace Axilium\SpryngV2;

use Axilium\SpryngV2\Dto\Message;
use Axilium\SpryngV2\Exception\ApiError;
use Axilium\SpryngV2\Exception\ApiException;
use Axilium\SpryngV2\Exception\AuthenticationException;
use Axilium\SpryngV2\Exception\ConflictException;
use Axilium\SpryngV2\Exception\NotFoundException;
use Axilium\SpryngV2\Exception\RateLimitException;
use Axilium\SpryngV2\Exception\SpryngException;
use Axilium\SpryngV2\Exception\ValidationException;
use Axilium\SpryngV2\Http\CurlTransport;
use Axilium\SpryngV2\Http\HttpResponse;
use Axilium\SpryngV2\Http\Transport;
use Axilium\SpryngV2\Resource\Balance;
use Axilium\SpryngV2\Resource\Contacts;
use Axilium\SpryngV2\Resource\Groups;
use Axilium\SpryngV2\Resource\Messages;
use Axilium\SpryngV2\Resource\OptOuts;
use Axilium\SpryngV2\Resource\Schedules;
use Axilium\SpryngV2\Resource\Templates;
use Axilium\SpryngV2\Resource\ThrottleSchedules;
use Axilium\SpryngV2\Resource\Webhooks;
use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;

/**
 * Entry point for the Spryng v2 API: authentication, URL building and the
 * mapping from HTTP status to exception. The endpoints themselves live on the
 * resource objects below.
 *
 *     $client = new SpryngClient($apiKey, accountReference: 'SPNL0000000');
 *     $result = $client->messages()->send(Message::text('+31612345678', 'Hi'));
 *
 * @see https://developer.spryng.nl
 * @see docs/openapi.yaml for the full description this is built against.
 */
final class SpryngClient
{
    public const VERSION  = '1.0.0';
    public const BASE_URL = 'https://api.spryng.nl/v2';

    /**
     * msgpit, a local catcher that answers like Spryng but sends nothing. The
     * hostname assumes msgpit runs as a Docker or Docksal service called "msgpit".
     *
     * @see https://github.com/raymondsteffann/msgpit
     */
    public const MSGPIT_BASE_URL = 'http://msgpit:8080/spryng/v2';

    private readonly Transport $transport;
    private readonly string $baseUrl;

    private ?Messages $messages = null;
    private ?Contacts $contacts = null;
    private ?Groups $groups = null;
    private ?Templates $templates = null;
    private ?Schedules $schedules = null;
    private ?ThrottleSchedules $throttleSchedules = null;
    private ?Webhooks $webhooks = null;
    private ?OptOuts $optOuts = null;
    private ?Balance $balance = null;

    /**
     * @param string      $apiKey           Created in the portal under Developer Tools.
     * @param string|null $accountReference Your account, e.g. SPNL0000000. Most
     *        GET endpoints require it as a header and the send endpoint wants it
     *        in the body, so setting it here saves passing it on every call.
     * @param string|null $baseUrl          Falls back to the SPRYNG_BASE_URL environment
     *        variable, then to BASE_URL.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $accountReference = null,
        ?Transport $transport = null,
        ?string $baseUrl = null,
    ) {
        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException('API key must not be empty.');
        }

        $this->transport = $transport ?? new CurlTransport();
        $this->baseUrl   = rtrim($baseUrl ?? self::environment('SPRYNG_BASE_URL') ?? self::BASE_URL, '/');
    }

    /**
     * Builds a client from SPRYNG_API_KEY, SPRYNG_ACCOUNT_REFERENCE and
     * SPRYNG_BASE_URL, so switching between Spryng and msgpit is configuration only.
     *
     * @throws InvalidArgumentException When SPRYNG_API_KEY is not set.
     */
    public static function fromEnvironment(?Transport $transport = null): self
    {
        $apiKey = self::environment('SPRYNG_API_KEY');

        if ($apiKey === null) {
            throw new InvalidArgumentException('The SPRYNG_API_KEY environment variable is not set.');
        }

        return new self($apiKey, self::environment('SPRYNG_ACCOUNT_REFERENCE'), $transport);
    }

    /**
     * Checks $_ENV and $_SERVER as well as getenv(), because dotenv loaders do
     * not always call putenv(). Empty values count as unset.
     */
    private static function environment(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    public function messages(): Messages
    {
        return $this->messages ??= new Messages($this);
    }

    public function contacts(): Contacts
    {
        return $this->contacts ??= new Contacts($this);
    }

    public function groups(): Groups
    {
        return $this->groups ??= new Groups($this);
    }

    public function templates(): Templates
    {
        return $this->templates ??= new Templates($this);
    }

    public function schedules(): Schedules
    {
        return $this->schedules ??= new Schedules($this);
    }

    public function throttleSchedules(): ThrottleSchedules
    {
        return $this->throttleSchedules ??= new ThrottleSchedules($this);
    }

    public function webhooks(): Webhooks
    {
        return $this->webhooks ??= new Webhooks($this);
    }

    public function optOuts(): OptOuts
    {
        return $this->optOuts ??= new OptOuts($this);
    }

    public function balance(): Balance
    {
        return $this->balance ??= new Balance($this);
    }

    /**
     * The account reference this client was constructed with, if any.
     */
    public function accountReference(): ?string
    {
        return $this->accountReference;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('POST', $path, $query, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('PUT', $path, $query, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('PATCH', $path, $query, $payload);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = [], array $payload = []): array
    {
        return $this->request('DELETE', $path, $query, $payload === [] ? null : $payload);
    }

    /**
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException     HTTP 400, the request was rejected.
     * @throws AuthenticationException HTTP 401 or 403.
     * @throws NotFoundException       HTTP 404.
     * @throws ConflictException       HTTP 409.
     * @throws RateLimitException      HTTP 429.
     * @throws ApiException            Any other non-2xx response.
     * @throws SpryngException         Network failure or an unencodable payload.
     */
    public function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $response = $this->transport->request(
            $method,
            $this->buildUrl($path, $query),
            $this->headers($payload !== null),
            $payload === null ? null : $this->encode($payload)
        );

        $this->guardAgainstErrors($response);

        return $response->json();
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== []);

        if ($query === []) {
            return $url;
        }

        return $url . '?' . $this->buildQueryString($query);
    }

    /**
     * Repeats a key per value, which is how the API reads its array filters:
     * ?contactId=a&contactId=b rather than ?contactId[]=a.
     *
     * @param array<string, mixed> $query
     */
    private function buildQueryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            foreach (is_array($value) ? array_values($value) : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($this->stringify($item));
            }
        }

        return implode('&', $parts);
    }

    /**
     * Query values arrive as whatever is natural to write at the call site:
     * a bool, an enum case, a DateTime. The API wants strings.
     */
    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof DateTimeInterface) {
            return Message::formatTimestamp($value);
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * @return array<string, string>
     */
    private function headers(bool $hasBody): array
    {
        $headers = [
            'X-Api-Key'  => $this->apiKey,
            'Accept'     => 'application/json',
            'User-Agent' => 'axilium-spryng-v2-api/' . self::VERSION . ' php/' . PHP_VERSION,
        ];

        if ($this->accountReference !== null) {
            $headers['AccountReference'] = $this->accountReference;
        }

        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        try {
            // PRESERVE_ZERO_FRACTION keeps an amount of 100.0 from going out as
            // the integer 100, which matters for the money fields.
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $e) {
            throw new SpryngException('Could not encode the request payload: ' . $e->getMessage(), 0, $e);
        }
    }

    private function guardAgainstErrors(HttpResponse $response): void
    {
        if ($response->isSuccessful()) {
            return;
        }

        $body   = $response->json();
        $errors = ApiError::listFromResponse($body);
        $status = $response->statusCode;

        $message = $errors !== []
            ? implode(' | ', array_map(static fn (ApiError $error): string => (string) $error, $errors))
            : sprintf('Unexpected response from the Spryng API (HTTP %d).', $status);

        throw match (true) {
            $status === 400 => new ValidationException($message, $status, $errors, $body),
            $status === 401,
            $status === 403 => new AuthenticationException($message, $status, $errors, $body),
            $status === 404 => new NotFoundException($message, $status, $errors, $body),
            $status === 409 => new ConflictException($message, $status, $errors, $body),
            $status === 429 => new RateLimitException(
                $message,
                $status,
                $errors,
                $body,
                $response->header('retry-after') !== null ? (int) $response->header('retry-after') : null
            ),
            default => new ApiException($message, $status, $errors, $body),
        };
    }
}
