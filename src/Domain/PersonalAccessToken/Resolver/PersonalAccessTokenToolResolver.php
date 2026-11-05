<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenTool;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;

final readonly class PersonalAccessTokenToolResolver
{
    public function __construct(
        private ?McpToolAccessChecker $toolAccessChecker = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveReadToolNames(): array
    {
        $toolAccessChecker = $this->toolAccessChecker;
        if (null === $toolAccessChecker) {
            return [];
        }

        return array_diff($toolAccessChecker->getToolNames(), $toolAccessChecker->getWriteToolNames()) |> array_values(...);
    }

    /**
     * @return list<string>
     */
    public function resolveWriteToolNames(): array
    {
        return $this->toolAccessChecker?->getWriteToolNames() ?? [];
    }

    /**
     * @return list<string>
     */
    public function resolveGrantableToolNames(): array
    {
        return $this->toolAccessChecker?->getPermittedToolNames() ?? [];
    }

    /**
     * @return list<PersonalAccessTokenTool>
     */
    public function resolveGrantableTools(bool $withWriteTools): array
    {
        $toolAccessChecker = $this->toolAccessChecker;
        if (null === $toolAccessChecker) {
            return [];
        }

        $toolNames = $toolAccessChecker->getPermittedToolNames();
        if (false === $withWriteTools) {
            $toolNames = array_diff($toolNames, $toolAccessChecker->getWriteToolNames()) |> array_values(...);
        }

        return array_map(
            static fn (string $toolName): PersonalAccessTokenTool => new PersonalAccessTokenTool(
                name: $toolName,
                writeTool: $toolAccessChecker->isWriteTool($toolName),
                category: $toolAccessChecker->getToolCategory($toolName),
            ),
            $toolNames,
        );
    }
}
