## [Unreleased]

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

## [6.0.0](https://github.com/anzusystems/auth-bundle/compare/5.0.0...6.0.0) (2026-07-22)

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
