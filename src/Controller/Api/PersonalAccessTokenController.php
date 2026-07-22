<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Controller\Api;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateDto;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateResult;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission;
use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseForbidden;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseNotFound;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseUnauthorized;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag('PersonalAccessToken')]
final class PersonalAccessTokenController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly PersonalAccessTokenFacade $personalAccessTokenFacade,
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[OAResponse([AbstractPersonalAccessToken::class]), OAResponseUnauthorized, OAResponseForbidden]
    public function getList(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_READ);

        return $this->okResponse(
            $this->personalAccessTokenRepo->findByUser($this->getUser())
        );
    }

    /**
     * @throws ValidationException|AppReadOnlyModeException|RandomException
     */
    #[OARequest(PersonalAccessTokenCreateDto::class), OAResponseCreated(PersonalAccessTokenCreateResult::class), OAResponseValidation, OAResponseUnauthorized, OAResponseForbidden]
    public function create(Request $request, #[SerializeParam] PersonalAccessTokenCreateDto $dto): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE);
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $result = $this->personalAccessTokenFacade->create(
            user: $this->getUser(),
            name: $dto->getName(),
            expiresAt: $dto->getExpiresAt(),
        );
        $this->logger->info('Personal access token created.', [
            'resource' => $result->personalAccessToken::getResourceName(),
            'personalAccessTokenId' => $result->personalAccessToken->getId(),
            'userId' => $this->getUser()
                ->getId(),
        ]);

        return $this->createdResponse($result);
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[OAParameterPath('id'), OAResponse(AbstractPersonalAccessToken::class), OAResponseNotFound, OAResponseUnauthorized, OAResponseForbidden]
    public function revoke(Request $request, int $id): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $personalAccessToken = $this->personalAccessTokenRepo->find($id);
        if (false === ($personalAccessToken instanceof AbstractPersonalAccessToken)) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE, $personalAccessToken);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $personalAccessToken);

        return $this->okResponse(
            $this->personalAccessTokenFacade->revoke($personalAccessToken)
        );
    }
}
