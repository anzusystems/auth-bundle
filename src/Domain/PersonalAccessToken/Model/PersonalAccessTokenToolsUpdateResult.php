<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;

final readonly class PersonalAccessTokenToolsUpdateResult
{
    /**
     * @param list<string>|null $previousReadTools
     * @param list<string> $previousExplicitReadTools
     * @param list<string> $previousWriteTools
     */
    public function __construct(
        public AbstractPersonalAccessToken $personalAccessToken,
        public ?array $previousReadTools,
        public array $previousExplicitReadTools,
        public array $previousWriteTools,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogContext(): array
    {
        return [
            'resource' => $this->personalAccessToken::getResourceName(),
            'personalAccessTokenId' => $this->personalAccessToken->getId(),
            'userId' => $this->personalAccessToken->getUser()
                ->getId(),
            'previousReadTools' => $this->previousReadTools,
            'previousExplicitReadTools' => $this->previousExplicitReadTools,
            'previousWriteTools' => $this->previousWriteTools,
            'readTools' => $this->personalAccessToken->getReadTools(),
            'explicitReadTools' => $this->personalAccessToken->getExplicitReadTools(),
            'writeTools' => $this->personalAccessToken->getWriteTools(),
        ];
    }
}
