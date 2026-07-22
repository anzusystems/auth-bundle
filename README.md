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

Opt-in personal access token (PAT) authentication: an sha256-hashed bearer token bound to a user, with expiration,
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
namespace and generate the migration with `doctrine:migrations:diff`.

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
`AnzuSystems\AuthBundle\Controller\Api\PersonalAccessTokenController` — add routes pointing at its
`getList`/`create`/`revoke` methods. Authorization uses the `auth_personalAccessToken_(create|read|revoke)`
permissions (see `AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission`); creation additionally requires
the `ROLE_MCP` role.

Console commands:

* `anzu:personal-access-token:create <userId> --name=<label> [--expires-at=...]` — prints the plaintext token once.
* `anzu:personal-access-token:notify-expiring` — daily cron; notifies owners of tokens expiring in 7 days or 1 day
  through `PersonalAccessTokenExpiryNotifierInterface` (no-op by default — alias your own implementation). The
  final-notice windows of consecutive runs overlap, so the implementation must be idempotent per
  (token, daysRemaining) pair — e.g. dispatch under an event name containing both.

When migrating from an app-level PAT implementation, rename existing permission grants to the
`auth_personalAccessToken_*` keys — grants stored under the old keys stop matching silently.
