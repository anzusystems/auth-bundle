<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Util;

use AnzuSystems\AuthBundle\Configuration\JwtConfiguration;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use DateTimeImmutable;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

final class JwtUtil
{
    public function __construct(
        private readonly JwtConfiguration $jwtConfiguration,
    ) {
    }

    /**
     * Can be used for creating a valid JWT. Useful especially for test environment.
     *
     * @param array<string, mixed> $claims
     *
     * @throws MissingConfigurationException
     */
    public function create(string $authId, ?DateTimeImmutable $expiresAt = null, array $claims = []): Plain
    {
        $privateCert = $this->jwtConfiguration->getPrivateCert();

        if (empty($privateCert)) {
            throw MissingConfigurationException::createForPrivateCertPath();
        }

        /** @psalm-var non-empty-string $authId */
        $builder = (new Builder(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates()))
            ->permittedFor($this->jwtConfiguration->getAudience())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($expiresAt ?: new DateTimeImmutable(sprintf('+%d seconds', $this->jwtConfiguration->getLifetime())))
            ->relatedTo($authId);

        /** @psalm-var non-empty-string $key */
        foreach ($claims as $key => $value) {
            $builder = $builder->withClaim($key, $value);
        }

        /** @var Plain */
        return $builder->getToken(
            $this->jwtConfiguration->getAlgorithm()->signer(),
            InMemory::plainText($privateCert)
        );
    }

    public function validate(Token\Plain $token): bool
    {
        /** @psalm-var non-empty-string $subject */
        $subject = (string) $token->claims()->get(RegisteredClaims::SUBJECT);

        $constraints = [
            new PermittedFor($this->jwtConfiguration->getAudience()),
            new RelatedTo($subject),
            new SignedWith(
                $this->jwtConfiguration->getAlgorithm()->signer(),
                InMemory::plainText($this->jwtConfiguration->getPublicCert())
            ),
            new LooseValidAt(SystemClock::fromUTC()),
        ];

        return (new Validator())->validate($token, ...$constraints);
    }
}
