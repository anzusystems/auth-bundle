AnzuSystems Auth Bundle by Petit Press a.s. (www.sme.sk)
=====

Provides authorization functionality among Anzusystems' projects.

---

## Installation

From within container execute the following command to download the latest version of the bundle:
```console
$ composer require anzusystems/auth-bundle --no-scripts
```

### Step 3: Use the Bundle

Configure the `AnzuAuthBundle` in `config/anzu_systems_auth.yaml`:

```yaml
anzu_systems_auth:
  cookie:
    domain: .anzusystems.localhost
    secure: false # use true for PROD environment!
  jwt:
    audience: anz
    algorithm: ES256 # enum (ES256|RS256), default "ES256"
    public_cert: '%env(base64:AUTH_JWT_PUBLIC_CERT)%' # string representation of a public certificate
    private_cert: '%env(base64:AUTH_JWT_PRIVATE_CERT)%' # string representation of a private certificate
  authorization:
    enabled: true
    refresh_token:
      storage:
        redis:
          service_id: SharedTokenStorageRedis # service id of \Redis instance
    auth_redirect_default_url: http://admin-dam.anzusystems.localhost
    auth_redirect_query_url_allowed_pattern: '^https?://(.*)\.anzusystems\.localhost(:\d{2,5})$'
    type: json_credentials
```

Configure the [SecurityBundle](https://symfony.com/doc/current/reference/configuration/security.html) in `config/security.yaml`:

```yaml
security:
  providers:
    app_user_provider_email:
      entity:
        class: App\Entity\User
        property: email

  auth:
    pattern: ^/api/auth/
    stateless: true
    provider: app_user_provider_email
    json_login:
      check_path: auth_login
      success_handler: AnzuSystems\AuthBundle\Security\AuthenticationSuccessHandler
      failure_handler: AnzuSystems\AuthBundle\Security\AuthenticationFailureHandler
    logout:
      path: auth_logout

  access_control:
    - { path: ^/api/auth/, roles: PUBLIC_ACCESS }
```

Configure routing:
```php
$routes
    ->import('@AnzuSystemsAuthBundle/Controller/Api/JsonCredentialsAuthController.php', type: 'attribute')
    ->prefix('/api/auth/');
```

## Personal access tokens

Opt-in personal access token (PAT) authentication: an sha256-hashed bearer token bound to a user, with optional expiration,
revocation, cached authentication, expiry notifications and management API. Disabled by default — a project that does
not enable it needs no schema or configuration changes after a bundle upgrade.

Enable it by subclassing the mapped superclass and pointing the config to it:

```php
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalAccessTokenRepository::class)]
#[ORM\Table(name: 'personal_access_token')]
#[ORM\Index(name: 'IDX_revokedAt_expiresAt', fields: ['revokedAt', 'expiresAt'])]
#[ORM\UniqueConstraint(name: 'UNIQ_tokenHash', fields: ['tokenHash'])]
class PersonalAccessToken extends AbstractPersonalAccessToken
{
}
```

```yaml
anzu_systems_auth:
  personal_access_token:
    enabled: true
    entity_class: App\Domain\PersonalAccessToken\Entity\PersonalAccessToken
    user_entity_class: App\Domain\User\Entity\User
    auth_cache_pool: 'some_redis.cache'
```

The `user` relation targets `AnzuSystems\Contracts\Entity\AnzuUser` — make sure doctrine
`resolve_target_entities` maps it to the project user class. Add the doctrine mapping for the bundle's `Entity`
namespace and generate the migration with `doctrine:migrations:diff`. `user_entity_class` must name the same class
as the common-bundle `settings.user_entity_class` — the authenticator and `CurrentAnzuUserProvider` would otherwise
load different user classes. The entity relies on constructor-less proxies, so use doctrine/orm 3 (lazy ghosts);
the ORM 2 legacy proxy strategy conflicts with the final entity constructor.

Wire the authenticator into a firewall protecting the API that accepts the tokens:

```yaml
security:
  firewalls:
    mcp:
      pattern: ^/api/mcp
      stateless: true
      provider: app_user_provider_id
      entry_point: AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator
      custom_authenticators:
        - AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator
```

Management API routes (list/create/revoke) are provided by
`AnzuSystems\AuthBundle\Controller\Api\PersonalAccessTokenController` attribute routes — import them with a prefix:

```php
$routes
    ->import('@AnzuSystemsAuthBundle/Controller/Api/PersonalAccessTokenController.php', type: 'attribute')
    ->prefix('/api/adm/v1');
```

The authenticator sets the `McpRateLimiter::TOKEN_ATTRIBUTE_KEY` (`pat_<id>`) and `McpRateLimiter::TOKEN_ATTRIBUTE_LIMIT`
(the token's `rateLimit`) attributes on the security token, so the common-bundle MCP rate limiter (`anzusystems/common-bundle`
`>=11.5`) buckets requests per personal access token and honours the per-token limit.

Authorization uses the `auth_personalAccessToken_(create|read|revoke)` permissions (see
`AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission`); creation additionally requires the role
configured via `create_role` (default `ROLE_MCP`).

Console commands:

* `anzu:personal-access-token:create <userId> --name=<label> [--expires-at=...] [--never-expires] [--rate-limit=N]` —
  prints the plaintext token once. `--never-expires` creates a token with `expiresAt = NULL` (skipped by the expiry
  notifications, mutually exclusive with `--expires-at`); `--rate-limit` stores a per-token MCP rate limit overriding
  the configured default (`null` = default). Both are command-only — the management API never sets them.
* `anzu:personal-access-token:notify-expiring` — daily cron; notifies owners of tokens expiring in 7 days or 1 day
  through `PersonalAccessTokenExpiryNotifierInterface` (no-op by default — alias your own implementation). The
  final-notice windows of consecutive runs overlap, so the implementation must be idempotent per
  (token, daysRemaining) pair — e.g. dispatch under an event name containing both.

When migrating from an app-level PAT implementation, rename existing permission grants to the
`auth_personalAccessToken_*` keys — grants stored under the old keys stop matching silently.
