<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Util;

use Symfony\Component\HttpFoundation\Request;

final class StatelessTokenUtil
{
    private const NOT_ENABLED_FALLBACK = 'anzusystems-auth';

    public function __construct(
        private readonly string $statelessTokenSalt,
        private readonly bool $enabled,
    ) {
    }

    public function createForRequest(Request $request): string
    {
        if ($this->enabled) {
            return urlencode(base64_encode(
                $this->createHashForRequest($request)
            ));
        }

        return self::NOT_ENABLED_FALLBACK;
    }

    /**
     * @param non-empty-string $hash
     */
    public function isValidForRequest(Request $request, string $hash): bool
    {
        if ($this->enabled) {
            return hash_equals(
                known_string: $this->createHashForRequest($request),
                user_string: base64_decode(urldecode($hash), strict: true),
            );
        }

        return self::NOT_ENABLED_FALLBACK === $hash;
    }

    /**
     * @param non-empty-string $hash
     */
    public function isNotValidForRequest(Request $request, string $hash): bool
    {
        return false === $this->isValidForRequest($request, $hash);
    }

    private function createHashForRequest(Request $request): string
    {
        return hash_hmac(
            algo: 'sha256',
            data: $request->headers->get('User-Agent') . $request->getClientIp(),
            key: $this->statelessTokenSalt,
        );
    }
}
