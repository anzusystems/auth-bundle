<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenTool;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;

final readonly class PersonalAccessTokenToolResolver
{
    public function __construct(
        private ?McpToolAccessChecker $toolAccessChecker = null,
        private ?McpToolDefinitions $toolDefinitions = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveReadToolNames(): array
    {
        $toolDefinitions = $this->toolDefinitions;
        if (null === $toolDefinitions) {
            return [];
        }

        return array_diff($toolDefinitions->getToolNames(), $toolDefinitions->getWriteToolNames()) |> array_values(...);
    }

    /**
     * @return list<string>
     */
    public function resolveWriteToolNames(): array
    {
        return $this->toolDefinitions?->getWriteToolNames() ?? [];
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
        $toolDefinitions = $this->toolDefinitions;
        if (null === $toolAccessChecker || null === $toolDefinitions) {
            return [];
        }

        $toolNames = $toolAccessChecker->getPermittedToolNames();
        if (false === $withWriteTools) {
            $toolNames = array_diff($toolNames, $toolDefinitions->getWriteToolNames()) |> array_values(...);
        }

        return array_map(
            static fn (string $toolName): PersonalAccessTokenTool => new PersonalAccessTokenTool(
                name: $toolName,
                writeTool: $toolAccessChecker->isWriteTool($toolName),
                category: $toolDefinitions->getToolCategory($toolName),
                explicitGrant: true === $toolDefinitions->find($toolName)?->explicitGrant,
            ),
            $toolNames,
        );
    }
}
