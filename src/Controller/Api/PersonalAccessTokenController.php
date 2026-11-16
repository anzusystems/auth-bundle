<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Controller\Api;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateDto;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateResult;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenTool;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenToolsUpdateDto;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/personal-access-token', name: 'auth_personal_access_token_')]
#[OA\Tag('PersonalAccessToken')]
final class PersonalAccessTokenController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly PersonalAccessTokenFacade $personalAccessTokenFacade,
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly PersonalAccessTokenToolResolver $toolResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponse([AbstractPersonalAccessToken::class]), OAResponseUnauthorized, OAResponseForbidden]
    public function getList(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_READ);

        return $this->okResponse(
            $this->personalAccessTokenRepo->findByUser($this->getUser())
        );
    }

    #[Route('/tools', name: 'get_tools', methods: [Request::METHOD_GET])]
    #[OAResponse([PersonalAccessTokenTool::class]), OAResponseUnauthorized, OAResponseForbidden]
    public function getTools(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE);

        return $this->okResponse(
            $this->toolResolver->resolveGrantableTools(
                withWriteTools: $this->isGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE_WITH_WRITE_TOOLS),
            )
        );
    }

    /**
     * @throws ValidationException|AppReadOnlyModeException|RandomException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(PersonalAccessTokenCreateDto::class), OAResponseCreated(PersonalAccessTokenCreateResult::class), OAResponseValidation, OAResponseUnauthorized, OAResponseForbidden]
    public function create(Request $request, #[SerializeParam] PersonalAccessTokenCreateDto $dto): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE);
        if (false === empty($dto->getWriteTools())) {
            $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE_WITH_WRITE_TOOLS);
        }
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $result = $this->personalAccessTokenFacade->createGranted($this->getUser(), $dto);
        $this->logger->info('Personal access token created.', [
            'resource' => $result->personalAccessToken::getResourceName(),
            'personalAccessTokenId' => $result->personalAccessToken->getId(),
            'userId' => $this->getUser()
                ->getId(),
            'writeTools' => $result->personalAccessToken->getWriteTools(),
            'readTools' => $result->personalAccessToken->getReadTools(),
            'explicitReadTools' => $result->personalAccessToken->getExplicitReadTools(),
        ]);

        return $this->createdResponse($result);
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route('/{id}/revoke', name: 'revoke', requirements: ['id' => Requirement::POSITIVE_INT], methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('id'), OAResponse(AbstractPersonalAccessToken::class), OAResponseNotFound, OAResponseUnauthorized, OAResponseForbidden]
    public function revoke(Request $request, int $id): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $personalAccessToken = $this->getPersonalAccessToken($id);
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_REVOKE, $personalAccessToken);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $personalAccessToken);

        return $this->okResponse(
            $this->personalAccessTokenFacade->revoke($personalAccessToken)
        );
    }

    /**
     * @throws ValidationException|AppReadOnlyModeException
     */
    #[Route('/{id}/tools', name: 'update_tools', requirements: ['id' => Requirement::POSITIVE_INT], methods: [Request::METHOD_PUT])]
    #[OAParameterPath('id'), OARequest(PersonalAccessTokenToolsUpdateDto::class), OAResponse(AbstractPersonalAccessToken::class), OAResponseValidation, OAResponseNotFound, OAResponseUnauthorized, OAResponseForbidden]
    public function updateTools(Request $request, int $id, #[SerializeParam] PersonalAccessTokenToolsUpdateDto $dto): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $personalAccessToken = $this->getPersonalAccessToken($id);
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE, $personalAccessToken);
        if (false === empty($dto->writeTools)) {
            $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE_WITH_WRITE_TOOLS);
        }
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $personalAccessToken);
        $result = $this->personalAccessTokenFacade->updateTools($personalAccessToken, $dto);
        $this->logger->info(PersonalAccessTokenFacade::LOG_MESSAGE_TOOLS_UPDATED, $result->getLogContext());

        return $this->okResponse($result->personalAccessToken);
    }

    private function getPersonalAccessToken(int $id): AbstractPersonalAccessToken
    {
        $personalAccessToken = $this->personalAccessTokenRepo->find($id);
        if (false === ($personalAccessToken instanceof AbstractPersonalAccessToken)) {
            throw $this->createNotFoundException();
        }

        return $personalAccessToken;
    }
}
