<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Controller;

use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\AuthBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\AuthBundle\Util\JwtUtil;
use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;
use Doctrine\DBAL\Connection;
use JsonException;
use LogicException;

abstract class AbstractControllerTestCase extends AnzuWebTestCase
{
    private const string AUTHORIZATION_SERVER_PARAMETER = 'HTTP_AUTHORIZATION';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->getConnection()
            ->rollBack();

        parent::tearDown();
    }

    protected function loginUser(int $userId): void
    {
        $jwt = self::getContainer()->get(JwtUtil::class)->create((string) $userId);

        $this->sendBearerToken($jwt->toString());
    }

    protected function sendBearerToken(string $token): void
    {
        self::$client->setServerParameter(self::AUTHORIZATION_SERVER_PARAMETER, 'Bearer ' . $token);
    }

    protected function getUser(int $userId): User
    {
        $user = self::getContainer()->get(UserRepository::class)->find($userId);
        if ($user instanceof User) {
            return $user;
        }

        throw new LogicException(sprintf('User "%d" does not exist, add it to the fixtures.', $userId));
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

    private function getConnection(): Connection
    {
        return self::getContainer()->get('doctrine.dbal.default_connection');
    }
}
