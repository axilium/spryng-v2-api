<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Enum\CharacterSet;
use Axilium\SpryngV2\Enum\MessageType;
use Axilium\SpryngV2\Exception\SpryngException;

/**
 * Reusable message templates. Send one by passing its id as the templateId of
 * a Message instead of text.
 *
 * @see https://developer.spryng.nl/templates/v2-templates-create
 */
final class Templates extends AbstractResource
{
    public const TYPE_TEXT = 'Text';

    /**
     * @param string $templateType The documentation allows "Text" only.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function create(
        string $name,
        string $content,
        string $templateType = self::TYPE_TEXT,
        ?MessageType $messageType = null,
        ?CharacterSet $characterSet = null,
    ): array {
        $payload = [
            'name'         => $name,
            'templateType' => $templateType,
            'content'      => $content,
        ];

        if ($messageType !== null) {
            $payload['messageType'] = $messageType->value;
        }

        if ($characterSet !== null) {
            $payload['channelSettings'] = ['sms' => ['characterSet' => $characterSet->value]];
        }

        return $this->data($this->client->post('/templates', $payload));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(string $templateId): array
    {
        return $this->data($this->client->get('/templates/' . rawurlencode($templateId)));
    }

    /**
     * Documented filters: PageSize, PageNo, sortBy, sortOrder, Search,
     * CreatedEndDate.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/templates', $filters));
    }

    /**
     * @param array<string, mixed> $template Any of name, templateType,
     *        messageType, content, channelSettings.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function update(string $templateId, array $template): array
    {
        return $this->data($this->client->put('/templates/' . rawurlencode($templateId), $template));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delete(string $templateId): array
    {
        return $this->client->delete('/templates/' . rawurlencode($templateId));
    }

    /**
     * Locks a template against further edits.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function lock(string $templateId): array
    {
        return $this->client->patch('/templates/' . rawurlencode($templateId) . '/lock');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function unlock(string $templateId): array
    {
        return $this->client->patch('/templates/' . rawurlencode($templateId) . '/unlock');
    }

    /**
     * Replaces the tags on a template.
     *
     * @param list<string> $tagIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function updateTags(string $templateId, array $tagIds): array
    {
        return $this->client->patch(
            '/templates/' . rawurlencode($templateId) . '/tags',
            ['tagIds' => array_values($tagIds)]
        );
    }

    /**
     * Copies a template to another account.
     *
     * @param string $destination The account reference to copy it to.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function copy(string $templateId, string $destination): array
    {
        return $this->data($this->client->post(
            '/templates/' . rawurlencode($templateId) . '/copy',
            ['destination' => $destination]
        ));
    }
}
