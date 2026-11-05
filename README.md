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
revocation, expiry notifications and management API. Disabled by default — a project that does
not enable it needs no schema or configuration changes after a bundle upgrade.

Enable it by subclassing the mapped superclass and pointing the config to it:

```php
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalAccessTokenRepository::class)]
#[ORM\Table(name: 'personal_access_token')]
#[ORM\Index(name: 'IDX_revokedAt_expiresAt', fields: ['revokedAt', 'expiresAt'])]
class PersonalAccessToken extends AbstractPersonalAccessToken
{
}
```

```yaml
anzu_systems_auth:
  personal_access_token:
    enabled: true
    entity_class: App\Domain\PersonalAccessToken\Entity\PersonalAccessToken
```

The `user` relation targets `AnzuSystems\Contracts\Entity\AnzuUser` — make sure doctrine
`resolve_target_entities` maps it to the project user class. Add the doctrine mapping for the bundle's `Entity`
namespace and generate the migration with `doctrine:migrations:diff`. The user class comes from the common-bundle
`settings.user_entity_class`. The entity relies on constructor-less proxies, so use doctrine/orm 3 (lazy ghosts);
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

Management API routes (list/create/revoke/tools) are provided by
`AnzuSystems\AuthBundle\Controller\Api\PersonalAccessTokenController` attribute routes — import them with a prefix:

```php
$routes
    ->import('@AnzuSystemsAuthBundle/Controller/Api/PersonalAccessTokenController.php', type: 'attribute')
    ->prefix('/api/adm/v1');
```

Authorization uses the `auth_personalAccessToken_(create|createWithWriteTools|read|revoke)` permissions (see
`AnzuSystems\AuthBundle\Security\PersonalAccessTokenPermission`); creation additionally requires the role
configured via `create_role` (default `ROLE_MCP`).

Declare these permissions in the host's common-bundle permission config (`anzu_systems_common.permissions.config`),
together with their translations, so the admin can grant them. Without the declaration the admin cannot grant them,
and `auth_personalAccessToken_createWithWriteTools` stays with super admins, who hold every permission checked through
`AbstractVoter` implicitly:

```yaml
anzu_systems_common:
  permissions:
    config:
      auth_personalAccessToken:
        read:
        create:
        createWithWriteTools:
        revoke:
    translation:
      subjects:
        auth_personalAccessToken:
          en: Personal access token
          sk: Osobný prístupový token
      actions:
        createWithWriteTools:
          en: Create with write tools
          sk: Vytvor so zápisovými nástrojmi
        revoke:
          en: Revoke
          sk: Zruš
```

Add `read` and `create` to `translation.actions` as well if the host does not translate them yet.

Every token says which MCP tools it may use, fixed at creation, in two separate lists:

* `writeTools` (`AbstractPersonalAccessToken::getWriteTools()`): the write tools, stored in the nullable JSON column
  `write_tools`. An empty list is a read-only token, and so is `NULL` in the column.
* `readTools` (`getReadTools()`): the read tools, stored in the nullable JSON column `read_tools`. `null` keeps every
  read tool the user may use, also ones added later, and `[]` gives none.

A token never gets more than its user: the tool permission is checked again on every call. An MCP preview tool
(`#[AsMcpTool(previewOf: …)]`) is never listed: a token gets it together with its write tool.

Every name must be a tool of the right kind in the common-bundle MCP configuration and may be listed once, otherwise
creation fails validation on that field. In an application without MCP enabled every name fails. The console command
relies on this check only and does not look at the user's permissions.

The management API also checks the creator: `POST <prefix>/personal-access-token` (`PersonalAccessTokenFacade::createGranted()`)
answers 422 on `readTools` or `writeTools` for a tool whose mapped permission the creator does not hold. A token with at
least one write tool additionally requires `auth_personalAccessToken_createWithWriteTools`, which super admins hold
implicitly, otherwise the answer is 403. `GET <prefix>/personal-access-token/tools` (allowed with
`auth_personalAccessToken_create`) returns the tools the current user may grant as
`[{"name": "list_published_articles", "writeTool": false, "category": "articles"}, …]`, so a creation form can offer
them. `category` is the category of the tool from `#[AsMcpTool(category: …)]`, camelCase, so a client can build a
translation key from it; the items come in `tool_permissions` order, not grouped by category.

The authenticator puts a common-bundle `McpToolScope` on the security token
(`PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE`), and `PersonalAccessTokenToolScopeProvider` is registered as
common-bundle's `McpToolScopeProviderInterface`, so the MCP server hides and refuses the other tools. A JWT or session
has no scope, so it gets no write tool, a super admin included.

Deleting a user: call `PersonalAccessTokenFacade::deleteByUser($user)` before removing the user entity — it deletes the
user's tokens in one flush. The `user` join column also declares `onDelete: CASCADE`,
but that is best-effort only: the `created_by`/`modified_by` columns of the user's own tokens still reference the user, so
the explicit `deleteByUser()` call is the contract.

Console commands:

* `anzu:personal-access-token:create <userId> --name=<label> [--expires-at=...] [--never-expires] [--write-tool=<name> ...] [--read-tool=<name> ...]` —
  prints the plaintext token once. `--never-expires` creates a token with `expiresAt = NULL` (skipped by the expiry
  notifications, mutually exclusive with `--expires-at`). It is command-only — the management API never sets it, so a
  never-expiring token can only be issued deliberately for a system user (e.g. a service integration). `--write-tool`
  is repeatable, one write tool per option; without it the token is read only. `--read-tool` is repeatable too; without
  it the token keeps every read tool its user may use. The command prints the granted tools.
* `anzu:personal-access-token:notify-expiring` — daily cron; notifies owners of tokens expiring in 7 days or 1 day
  through `PersonalAccessTokenExpiryNotifierInterface` (no-op by default — alias your own implementation). The
  final-notice windows of consecutive runs overlap, so the implementation must be idempotent per
  (token, daysRemaining) pair — e.g. dispatch under an event name containing both.

When migrating from an app-level PAT implementation, rename existing permission grants to the
`auth_personalAccessToken_*` keys — grants stored under the old keys stop matching silently.
