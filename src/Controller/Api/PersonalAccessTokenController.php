<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Controller\Api;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateDto;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateResult;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenWriteToolResolver;
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
    private const string WRITE_TOOLS_NOT_GRANTABLE_MESSAGE = 'A token can grant only the write tools whose tool permission you hold.';

    public function __construct(
        private readonly PersonalAccessTokenFacade $personalAccessTokenFacade,
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly PersonalAccessTokenWriteToolResolver $writeToolResolver,
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

    #[Route('/write-tools', name: 'get_write_tools', methods: [Request::METHOD_GET])]
    #[OA\Response(response: JsonResponse::HTTP_OK, description: 'Names of the MCP write tools the current user may grant to a token', content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string'))), OAResponseUnauthorized, OAResponseForbidden]
    public function getWriteTools(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE_WITH_WRITE_TOOLS);

        return $this->okResponse(
            $this->writeToolResolver->resolveGrantableWriteTools()
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
        if ($this->hasUngrantableWriteTool($dto->getWriteTools())) {
            throw $this->createAccessDeniedException(self::WRITE_TOOLS_NOT_GRANTABLE_MESSAGE);
        }
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $result = $this->personalAccessTokenFacade->create(
            user: $this->getUser(),
            name: $dto->getName(),
            expiresAt: $dto->getExpiresAt(),
            writeTools: $dto->getWriteTools(),
        );
        $this->logger->info('Personal access token created.', [
            'resource' => $result->personalAccessToken::getResourceName(),
            'personalAccessTokenId' => $result->personalAccessToken->getId(),
            'userId' => $this->getUser()
                ->getId(),
            'writeTools' => $result->personalAccessToken->getWriteTools(),
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

    /**
     * @param array<mixed> $writeTools
     */
    private function hasUngrantableWriteTool(array $writeTools): bool
    {
        $grantableWriteTools = $this->writeToolResolver->resolveGrantableWriteTools();

        return array_any(
            $writeTools,
            static fn (mixed $writeTool): bool => false === in_array(needle: $writeTool, haystack: $grantableWriteTools, strict: true),
        );
    }
}
