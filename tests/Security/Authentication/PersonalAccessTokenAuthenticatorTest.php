<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security\Authentication;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class PersonalAccessTokenAuthenticatorTest extends TestCase
{
    private const string PLAIN_TOKEN = AbstractPersonalAccessToken::TOKEN_PREFIX . 'a1b2c3d4';

    private PersonalAccessTokenAuthenticator $authenticator;

    public static function setUpBeforeClass(): void
    {
        AnzuApp::init(
            appNamespace: 'anzusystems',
            appSystem: 'authbundle',
            appVersion: 'test',
            appReadOnlyMode: false,
            projectDir: sys_get_temp_dir(),
            appEnv: 'test',
        );
    }

    protected function setUp(): void
    {
        $repositoryEntityManager = $this->createStub(EntityManagerInterface::class);
        $repositoryEntityManager->method('getClassMetadata')
            ->willReturn(new ClassMetadata(PersonalAccessToken::class));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->willReturn($repositoryEntityManager);

        $manager = new PersonalAccessTokenManager();
        $manager->setEntityManager($this->createStub(EntityManagerInterface::class));
        $manager->setCurrentAnzuUserProvider($this->createStub(CurrentAnzuUserProvider::class));

        $this->authenticator = new PersonalAccessTokenAuthenticator(
            new PersonalAccessTokenRepository($registry, PersonalAccessToken::class),
            $manager,
        );
    }

    public function testSupportsOnlyBearerTokensWithPatPrefix(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/api/mcp')));
        self::assertFalse($this->authenticator->supports($this->createRequest('Basic dXNlcjpwYXNz')));
        self::assertFalse($this->authenticator->supports($this->createRequest('Bearer some-jwt-token')));
        self::assertTrue($this->authenticator->supports($this->createRequest('Bearer ' . self::PLAIN_TOKEN)));
        self::assertTrue($this->authenticator->supports($this->createRequest('bearer ' . self::PLAIN_TOKEN)));
    }

    public function testUnauthorizedResponseContainsWwwAuthenticateHeader(): void
    {
        $response = $this->authenticator->start(Request::create('/api/mcp'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
    }

    public function testMissingAndInvalidTokenAreDistinguishableInTheResponseBody(): void
    {
        $missing = (string) $this->authenticator->start(Request::create('/api/mcp'))->getContent();
        $invalid = (string) $this->authenticator
            ->onAuthenticationFailure(Request::create('/api/mcp'), new AuthenticationException())
            ->getContent();

        self::assertStringContainsString(AbstractPersonalAccessToken::TOKEN_PREFIX, $missing);
        self::assertStringContainsString('invalid, revoked or expired', $invalid);
        self::assertNotSame($missing, $invalid);
    }

    private function createRequest(string $authorizationHeader): Request
    {
        $request = Request::create('/api/mcp');
        $request->headers->set('Authorization', $authorizationHeader);

        return $request;
    }
}
