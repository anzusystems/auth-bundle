<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\HttpFoundation\Request;

final class DeviceDto
{
    private const DEVICE_INFO_HEADERS = [
        'User-Agent',
        'Sec-CH-UA',
        'Sec-CH-UA-Arch',
        'Sec-CH-UA-Bitness',
        'Sec-CH-UA-Mobile',
        'Sec-CH-UA-Model',
        'Sec-CH-UA-Platform',
        'Sec-CH-UA-Full-Version-List',
    ];

    #[Serialize]
    private string $deviceId;

    #[Serialize]
    private string $ip;

    #[Serialize]
    private array $info;

    public static function createFromRequest(string $deviceId, Request $request): self
    {
        $headers = [];
        foreach (self::DEVICE_INFO_HEADERS as $header) {
            if ($request->headers->has($header)) {
                $headers[$header] = $request->headers->get($header);
            }
        }

        return (new self())
            ->setDeviceId($deviceId)
            ->setIp($request->getClientIp() ?? '')
            ->setInfo(array_filter($headers))
        ;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): self
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function setInfo(array $info): self
    {
        $this->info = $info;

        return $this;
    }
}
