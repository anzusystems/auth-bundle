<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Security\Authentication;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class PersonalAccessTokenAuthenticatorTest extends TestCase
{
    private const string PLAIN_TOKEN = AbstractPersonalAccessToken::TOKEN_PREFIX . 'a1b2c3d4';
    private const int USER_ID = 42;
    private const string FIREWALL_NAME = 'main';
    private const string READ_TOOL = 'example_read';
    private const string EXPLICIT_READ_TOOL = 'example_explicit_read';
    private const string WRITE_TOOL = 'example_write';

    private PersonalAccessTokenAuthenticator $authenticator;
    private ?AbstractPersonalAccessToken $storedToken = null;

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
        $query = $this->createStub(Query::class);
        $query->method('getOneOrNullResult')
            ->willReturnCallback(fn (): ?AbstractPersonalAccessToken => $this->storedToken);
        $queryBuilder = $this->createStub(QueryBuilder::class);
        foreach (['select', 'from', 'where', 'andWhere', 'setParameter'] as $fluentMethod) {
            $queryBuilder->method($fluentMethod)
                ->willReturnSelf();
        }
        $queryBuilder->method('getQuery')
            ->willReturn($query);

        $repositoryEntityManager = $this->createStub(EntityManagerInterface::class);
        $repositoryEntityManager->method('getClassMetadata')
            ->willReturn(new ClassMetadata(PersonalAccessToken::class));
        $repositoryEntityManager->method('createQueryBuilder')
            ->willReturn($queryBuilder);
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
        $this->storedToken = null;
    }

    public function testSupportsOnlyBearerTokensWithPatPrefix(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/api/mcp')));
        self::assertFalse($this->authenticator->supports($this->createRequest('Basic dXNlcjpwYXNz')));
        self::assertFalse($this->authenticator->supports($this->createRequest('Bearer some-jwt-token')));
        self::assertTrue($this->authenticator->supports($this->createRequest('Bearer ' . self::PLAIN_TOKEN)));
        self::assertTrue($this->authenticator->supports($this->createRequest('bearer ' . self::PLAIN_TOKEN)));
    }

    public function testAuthenticatePutsTheToolsOfTheTokenOnThePassport(): void
    {
        $user = $this->createUser(enabled: true);
        $this->storedToken = new PersonalAccessToken()
            ->setUser($user)
            ->setReadTools([self::READ_TOOL])
            ->setExplicitReadTools([self::EXPLICIT_READ_TOOL])
            ->setWriteTools([self::WRITE_TOOL]);

        $passport = $this->authenticator->authenticate($this->createRequest('Bearer ' . self::PLAIN_TOKEN));

        self::assertSame($user, $passport->getUser());
        self::assertEquals(
            new McpToolScope(
                readToolNames: [self::READ_TOOL],
                writeToolNames: [self::WRITE_TOOL],
                explicitToolNames: [self::EXPLICIT_READ_TOOL],
            ),
            $passport->getAttribute(PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE),
        );
    }

    public function testAuthenticateKeepsEveryReadToolNextToAnExplicitReadTool(): void
    {
        $this->storedToken = new PersonalAccessToken()
            ->setUser($this->createUser(enabled: true))
            ->setExplicitReadTools([self::EXPLICIT_READ_TOOL]);

        $passport = $this->authenticator->authenticate($this->createRequest('Bearer ' . self::PLAIN_TOKEN));

        self::assertEquals(
            new McpToolScope(explicitToolNames: [self::EXPLICIT_READ_TOOL]),
            $passport->getAttribute(PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE),
        );
    }

    public function testAuthenticateRejectsDisabledUser(): void
    {
        $this->storedToken = new PersonalAccessToken()
            ->setUser($this->createUser(enabled: false));

        $this->expectException(AuthenticationException::class);

        $this->authenticator->authenticate($this->createRequest('Bearer ' . self::PLAIN_TOKEN));
    }

    public function testAuthenticateRejectsUnknownToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->authenticator->authenticate($this->createRequest('Bearer ' . self::PLAIN_TOKEN));
    }

    public function testCreateTokenCarriesTheToolScopeOfThePassport(): void
    {
        $user = $this->createUser(enabled: true);
        $toolScope = new McpToolScope(readToolNames: [self::READ_TOOL], writeToolNames: [self::WRITE_TOOL]);
        $passport = new SelfValidatingPassport(
            new UserBadge((string) self::USER_ID, static fn (): AnzuUser => $user)
        );
        $passport->setAttribute(PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE, $toolScope);

        $token = $this->authenticator->createToken($passport, self::FIREWALL_NAME);

        self::assertSame($toolScope, $token->getAttribute(PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE));
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

    private function createUser(bool $enabled): AnzuUser
    {
        return $this->createConfiguredStub(AnzuUser::class, [
            'getId' => self::USER_ID,
            'isEnabled' => $enabled,
            'getRoles' => [],
        ]);
    }

    private function createRequest(string $authorizationHeader): Request
    {
        $request = Request::create('/api/mcp');
        $request->headers->set('Authorization', $authorizationHeader);

        return $request;
    }
}
