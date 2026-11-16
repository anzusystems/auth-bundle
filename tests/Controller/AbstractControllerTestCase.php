<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Controller;

use AnzuSystems\AuthBundle\Tests\DatabaseTestTrait;
use AnzuSystems\AuthBundle\Util\JwtUtil;
use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;
use JsonException;

abstract class AbstractControllerTestCase extends AnzuWebTestCase
{
    use DatabaseTestTrait;

    private const string AUTHORIZATION_SERVER_PARAMETER = 'HTTP_AUTHORIZATION';

    protected function loginUser(int $userId): void
    {
        $jwt = self::getContainer()->get(JwtUtil::class)->create((string) $userId);

        $this->sendBearerToken($jwt->toString());
    }

    protected function sendBearerToken(string $token): void
    {
        self::$client->setServerParameter(self::AUTHORIZATION_SERVER_PARAMETER, 'Bearer ' . $token);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<int|string, mixed>
     *
     * @throws JsonException
     */
    protected function requestJson(string $method, string $uri, ?array $payload = null): array
    {
        self::$client->request(
            method: $method,
            uri: $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: is_array($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        );
        $content = (string) self::$client->getResponse()->getContent();
        if (StringHelper::isEmpty($content)) {
            return [];
        }

        return json_decode(json: $content, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
