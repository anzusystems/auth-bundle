<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Util;

use Symfony\Component\HttpFoundation\Request;

final class StatelessTokenUtil
{
    public function __construct(
        private readonly string $statelessTokenSalt,
    ) {
    }

    public function createForRequest(Request $request): string
    {
        return urldecode(base64_encode(
            $this->createHashForRequest($request)
        ));
    }

    /**
     * @param non-empty-string $hash
     */
    public function isValidForRequest(Request $request, string $hash): bool
    {
        return hash_equals(
            known_string: $this->createHashForRequest($request),
            user_string: urldecode(base64_decode($hash, strict: true)),
        );
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
