<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;

final class MissingConfigurationException extends AnzuException
{
    private const PRIVATE_CERT_PATH_CONFIG_NAME = 'private_cert_path';

    public static function createForPrivateCertPath(): self
    {
        return new self(sprintf('Missing "%s" configuration', self::PRIVATE_CERT_PATH_CONFIG_NAME));
    }
}
