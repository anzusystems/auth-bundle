<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security;

final class PersonalAccessTokenPermission
{
    public const string ROLE_MCP = 'ROLE_MCP';

    public const string PERSONAL_ACCESS_TOKEN_CREATE = 'auth_personalAccessToken_create';
    public const string PERSONAL_ACCESS_TOKEN_READ = 'auth_personalAccessToken_read';
    public const string PERSONAL_ACCESS_TOKEN_REVOKE = 'auth_personalAccessToken_revoke';
}
