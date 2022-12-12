<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Configuration;

use AnzuSystems\AuthBundle\Model\Enum\JwtAlgorithm;

final class JwtConfiguration
{
    /**
     * @param non-empty-string $audience
     * @param non-empty-string $algorithm
     * @param non-empty-string $publicCert
     */
    public function __construct(
        private readonly string $audience,
        private readonly string $algorithm,
        private readonly string $publicCert,
        private readonly string $privateCert,
        private readonly int $lifetime,
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getAudience(): string
    {
        return $this->audience;
    }

    public function getAlgorithm(): JwtAlgorithm
    {
        return JwtAlgorithm::from($this->algorithm);
    }

    /**
     * @return non-empty-string
     */
    public function getPublicCert(): string
    {
        return $this->publicCert;
    }

    public function getPrivateCert(): string
    {
        return $this->privateCert;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }
}
