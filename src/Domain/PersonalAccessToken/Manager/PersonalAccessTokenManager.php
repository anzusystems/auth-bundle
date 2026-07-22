<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\Contracts\AnzuApp;

final class PersonalAccessTokenManager extends AbstractManager
{
    public function create(AbstractPersonalAccessToken $personalAccessToken, bool $flush = true): AbstractPersonalAccessToken
    {
        $this->trackCreation($personalAccessToken);
        $this->entityManager->persist($personalAccessToken);
        $this->flush($flush);

        return $personalAccessToken;
    }

    public function revoke(AbstractPersonalAccessToken $personalAccessToken, bool $flush = true): AbstractPersonalAccessToken
    {
        $this->trackModification($personalAccessToken);
        $personalAccessToken->setRevokedAt(AnzuApp::date());
        $this->flush($flush);

        return $personalAccessToken;
    }

    public function updateLastUsedAt(AbstractPersonalAccessToken $personalAccessToken, bool $flush = true): AbstractPersonalAccessToken
    {
        $personalAccessToken->setLastUsedAt(AnzuApp::date());
        $this->flush($flush);

        return $personalAccessToken;
    }
}
