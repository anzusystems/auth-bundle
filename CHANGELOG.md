## Unreleased

### Features
* Personal access tokens say which MCP tools they may use, fixed at creation: `writeTools` (`list<string>`, `[]` is a read-only token) and `readTools` (`?list<string>`, `null` keeps every read tool the user may use). The management API accepts and returns both. Every name must be a tool of that kind in the MCP configuration, listed once, otherwise validation fails on that field. Add the columns with a migration.
* Creating a token with at least one write tool through the management API requires the new `auth_personalAccessToken_createWithWriteTools` permission (`PersonalAccessTokenPermission::PERSONAL_ACCESS_TOKEN_CREATE_WITH_WRITE_TOOLS`) on top of `auth_personalAccessToken_create`.
* `PersonalAccessTokenFacade::createGranted()`, used by the management API, answers 422 on `readTools` or `writeTools` for a tool whose mapped permission the creator does not hold. The console command uses `create()`, which checks the configuration only.
* New `GET personal-access-token/tools` route (`auth_personal_access_token_get_tools`), allowed with `auth_personalAccessToken_create`, returns the MCP tools the current user may grant, with `writeTool`, `category` and `explicitGrant`.
* A read tool declared with `#[AsMcpTool(explicitGrant: true)]` is not part of `readTools: null`; a token gets it only when `readTools` names it. The tools route marks it with `explicitGrant: true`, so a creation form can leave it out of "every read tool" and send a list when the user picks it.
* `PersonalAccessTokenAuthenticator` puts a common-bundle `McpToolScope` on the security token under `TOOL_SCOPE_ATTRIBUTE`. The new `PersonalAccessTokenToolScopeProvider` is registered as common-bundle's `McpToolScopeProviderInterface`, so the MCP server hides and refuses the other tools. It throws a `LogicException` when the attribute is not a `McpToolScope`.
* `anzu:personal-access-token:create` takes `--write-tool`, `--read-tool` and `--no-read-tools`.
* New `anzu:personal-access-token:list <userId>` and `anzu:personal-access-token:revoke <id>` commands, so an operator can find and revoke a token of any user.

### Changes
* After a rollback to a version without `readTools`, a token with narrowed read tools sees every read tool its user may use until the new version is back.
* BC change: the authentication cache is gone, `PersonalAccessTokenAuthenticator` reads the token from the database on every request, so a revocation applies at once. `PersonalAccessTokenAuthCache`, `CachedPersonalAccessToken` and the `personal_access_token.auth_cache_pool` option are removed; remove the option and the pool from the host config.
* BC change: `PersonalAccessTokenFacade` requires `PersonalAccessTokenToolResolver` (autowired); code constructing it by hand has to change.
* BC change: the `personal_access_token.user_entity_class` option is gone. The user class comes from the common-bundle `settings.user_entity_class`; remove the option from the host config.
* BC change: `tokenHash` is unique through the mapped superclass, so a host removes its own `UniqueConstraint` on `tokenHash` and renames the index with a migration.
* BC change: `anzusystems/common-bundle` requirement raised to `^12.0` (`McpToolScope`, `getWriteToolNames()`, `getPermittedToolNames()`, `getToolCategory()` and `McpToolDefinition::$explicitGrant`).
* BC change: the bundle requires PHP 8.5, as `anzusystems/common-bundle` on this release line does.

## [5.2.1](https://github.com/anzusystems/auth-bundle/compare/5.2.0...5.2.1) (2026-09-16)

### Security
* Successful `POST login` and `POST refresh-token` requests are no longer written to the audit log. The OAuth2 `authorize` callback is excluded as well, although a GET is not audited under the default `logged_methods`.
* Requests rejected by `AuthenticationFailureHandler`, a failed `login` among them, are no longer written either. Failed `refresh-token` attempts are still logged.

## [5.2.0](https://github.com/anzusystems/auth-bundle/compare/5.1.0...5.2.0) (2026-09-10)

### Features
* The two 401 states of `PersonalAccessTokenAuthenticator` are told apart in the response body: `start()` (no `Authorization` header, or one that does not carry a personal access token) answers "Missing personal access token…", `onAuthenticationFailure()` (header present, token rejected) answers "The personal access token is invalid, revoked or expired…". Status code and `WWW-Authenticate` are unchanged. Callers could previously not tell a header that never reached the application from a bad token.
* `PersonalAccessTokenFacade::create()` rejects `neverExpires` combined with an `expiresAt` with a `ValidationException` on `expiresAt` instead of an `InvalidArgumentException`, so the console command reports it through its existing validation handling. Never-expiring tokens stay console-only — the management API cannot request one, so they can be issued only deliberately for a system user.
* `AbstractPersonalAccessToken::expiresAt` is nullable — `NULL` means the token never expires (active in `findOneActiveByTokenHash()`, skipped by `anzu:personal-access-token:notify-expiring`). Hosts must add a migration: `expires_at DATETIME DEFAULT NULL`, `user_id` foreign key `ON DELETE CASCADE`.
* `anzu:personal-access-token:create` gained `--never-expires` (mutually exclusive with `--expires-at`); `PersonalAccessTokenFacade::create()` gained `bool $neverExpires`. The management API keeps creating expiring tokens.
* `PersonalAccessTokenFacade::deleteByUser()` + `PersonalAccessTokenManager::delete()` remove all tokens of a user and invalidate their auth cache entries — call it before deleting the user (the only reliable path — `created_by`/`modified_by` of the user's own tokens still reference the user); the `user` join column additionally declares `onDelete: CASCADE` as a best-effort database-level cleanup (hosts must update the foreign key in their migration).
* `PersonalAccessTokenAuthCache` caches a `CachedPersonalAccessToken` (token id, user id) under a new key prefix instead of the bare user id.

### Changes
* BC change: `anzusystems/common-bundle` requirement raised to `^11.5`.
* BC change: `PersonalAccessTokenFacade` constructor gained `PersonalAccessTokenRepository $repository` (autowired).
* BC change: `AbstractPersonalAccessToken::getExpiresAt()` returns `?DateTimeImmutable`, `setExpiresAt()` accepts `null`; `PersonalAccessTokenAuthCache::getUserId()/storeUserId()` replaced by `getToken()/storeToken()`.
* A cached token whose user entity no longer exists now fails authentication instead of falling back to the database lookup.

## [5.1.0](https://github.com/anzusystems/auth-bundle/compare/5.0.0...5.1.0) (2026-08-05)

### Features
* New opt-in `personal_access_token` config section (disabled by default — upgrading without enabling it requires no schema or configuration changes): sha256-hashed bearer tokens bound to a user with expiration (default 2 months, max 1 year), revocation, a versioned auth cache with the entry lifetime capped at the token expiry, throttled `lastUsedAt` tracking and a read-only-mode guard.
* `AbstractPersonalAccessToken` mapped superclass — the host subclasses it, owns the table, indexes (`UNIQ_tokenHash`, `IDX_revokedAt_expiresAt`) and migration; the `user` relation targets the contracts `AnzuUser` via doctrine `resolve_target_entities`. Configured via `entity_class`, `user_entity_class` and `auth_cache_pool`.
* `PersonalAccessTokenAuthenticator` (`Security/Authentication`) — stateless bearer authenticator claiming only tokens with the `anzu_pat_` prefix, cache-first user lookup, uniform information-free 401 responses with a `WWW-Authenticate: Bearer` header.
* `PersonalAccessTokenVoter` + `PersonalAccessTokenPermission` — `auth_personalAccessToken_(create|read|revoke)` permissions with owner-only access and creation gated by `ROLE_MCP`.
* `PersonalAccessTokenController` (`Controller/Api`) — list own tokens, create (plaintext returned exactly once, request excluded from audit logs) and revoke; the host defines the routes.
* Console commands `anzu:personal-access-token:create` (prints the plaintext token once) and `anzu:personal-access-token:notify-expiring` (daily cron notifying owners 7 days and 1 day before expiry through `PersonalAccessTokenExpiryNotifierInterface`; no-op by default — alias your own implementation, which must be idempotent per (token, daysRemaining) pair because consecutive final-notice windows overlap).

### Changes
* BC change: `anzusystems/common-bundle` requirement floor raised to `^9.4` (uses `AuditLogResourceHelper::excludeFromAuditLogs()`), with `^12.0` allowed.
* BC change: new explicit `symfony/console` requirement `^7.3|^8.0` (invokable command support).
