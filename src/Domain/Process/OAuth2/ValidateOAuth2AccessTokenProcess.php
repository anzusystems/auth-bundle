<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process\OAuth2;

use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Model\Enum\JwtAlgorithm;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\NoConstraintsGiven;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;

final class ValidateOAuth2AccessTokenProcess
{
    public function __construct(
        private readonly OAuth2Configuration $OAuth2Configuration,
    ) {
    }

    /**
     * @throws InvalidJwtException
     */
    public function execute(Plain $token): void
    {
        if (empty($this->OAuth2Configuration->getSsoPublicCert())) {
            throw new InvalidJwtException('Please configure SSO public certificate.');
        }

        /** @psalm-var non-empty-string $subject */
        $subject = (string) $token->claims()->get(RegisteredClaims::SUBJECT);

        $constraints = [
            new PermittedFor($this->OAuth2Configuration->getSsoClientId()),
            new RelatedTo($subject),
            new SignedWith(
                JwtAlgorithm::from((string) $token->headers()->get('alg'))->signer(),
                InMemory::plainText($this->OAuth2Configuration->getSsoPublicCert())
            ),
            new LooseValidAt(SystemClock::fromUTC()),
        ];

        try {
            (new Validator())->assert($token, ...$constraints);
        } catch (RequiredConstraintsViolated | NoConstraintsGiven $exception) {
            throw InvalidJwtException::create($token->toString(), $exception);
        }
    }
}
