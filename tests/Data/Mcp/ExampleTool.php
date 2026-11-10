<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Mcp;

/**
 * Each tool is guarded by a permission of the same name.
 */
final class ExampleTool
{
    public const string CATEGORY = 'example';

    public const string READ = 'example_read';
    public const string EXPLICIT_READ = 'example_explicit_read';
    public const string RESTRICTED_READ = 'example_restricted_read';
    public const string WRITE = 'example_write';
    public const string RESTRICTED_WRITE = 'example_restricted_write';

    public const array READ_ONLY_BY_NAME = [
        self::READ => true,
        self::EXPLICIT_READ => true,
        self::RESTRICTED_READ => true,
        self::WRITE => false,
        self::RESTRICTED_WRITE => false,
    ];
}
