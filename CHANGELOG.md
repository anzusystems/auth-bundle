## [Unreleased]

### Features
* `AbstractPersonalAccessToken::expiresAt` is nullable — `NULL` means the token never expires (active in `findOneActiveByTokenHash()`, skipped by `anzu:personal-access-token:notify-expiring`). New `rateLimit` column (`?int`, not serialized) — a per-token MCP rate limit overriding the configured default. Hosts must add a migration: `expires_at DATETIME DEFAULT NULL`, `rate_limit INT UNSIGNED DEFAULT NULL`, `user_id` foreign key `ON DELETE CASCADE`.
* `anzu:personal-access-token:create` gained `--never-expires` (mutually exclusive with `--expires-at`) and `--rate-limit=N`; `PersonalAccessTokenFacade::create()` gained `?int $rateLimit` and `bool $neverExpires`. The management API keeps creating expiring tokens without a rate limit.
* `PersonalAccessTokenFacade::deleteByUser()` + `PersonalAccessTokenManager::delete()` remove all tokens of a user and invalidate their auth cache entries — call it before deleting the user (the only reliable path — `created_by`/`modified_by` of the user's own tokens still reference the user); the `user` join column additionally declares `onDelete: CASCADE` as a best-effort database-level cleanup (hosts must update the foreign key in their migration).
* `PersonalAccessTokenAuthenticator` sets `McpRateLimiter::TOKEN_ATTRIBUTE_KEY` (`pat_<id>`) and `McpRateLimiter::TOKEN_ATTRIBUTE_LIMIT` on the security token; `PersonalAccessTokenAuthCache` caches a `CachedPersonalAccessToken` (token id, user id, rate limit) under a new key prefix instead of the bare user id.

### Changes
* BC change: `anzusystems/common-bundle` requirement raised to `^11.5` (the authenticator uses `McpRateLimiter::TOKEN_ATTRIBUTE_*`).
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
