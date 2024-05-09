<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Serializer\Handler\Handlers;

use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;

final class JwtHandler extends AbstractHandler
{
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Plain) {
            return $value->toString();
        }

        throw new SerializerException(
            sprintf('Value must be a type of (%s)', Plain::class)
        );
    }

    public function deserialize(mixed $value, Metadata $metadata): ?Plain
    {
        if (null === $value) {
            return null;
        }

        if (false === is_string($value)) {
            throw new SerializerException('Value must be a type of valid JWT string');
        }

        try {
            /** @var Plain $token */
            $token = (new Parser(new JoseEncoder()))->parse($value);

            return $token;
        } catch (Exception) {
            throw new SerializerException(sprintf(
                'Value must be a type of valid JWT string, (%s) provided',
                $value,
            ));
        }
    }
}
