<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Util;

use AnzuSystems\CommonBundle\Helper\PasswordHelper;
use Symfony\Component\HttpFoundation\Request;

final class StatelessTokenUtil
{
    public function __construct(
        private readonly string $statelessTokenSalt,
    ) {
    }

    public function createForRequest(Request $request): string
    {
        return base64_encode(PasswordHelper::passwordHash(
            $this->createPlainForRequest($request)
        ));
    }

    /**
     * @param non-empty-string $hash
     */
    public function isValidForRequest(Request $request, string $hash): bool
    {
        $token = $this->createPlainForRequest($request);

        return password_verify($token, base64_decode($hash, strict: true));
    }

    /**
     * @param non-empty-string $hash
     */
    public function isNotValidForRequest(Request $request, string $hash): bool
    {
        return false === $this->isValidForRequest($request, $hash);
    }

    private function createPlainForRequest(Request $request): string
    {
        return $request->headers->get('User-Agent') . $request->getClientIp() . $this->statelessTokenSalt;
    }
}
