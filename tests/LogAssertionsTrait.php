<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests;

use LogicException;
use Monolog\Handler\TestHandler;
use Monolog\Level;

trait LogAssertionsTrait
{
    private const string LOG_HANDLER_ID = 'monolog.handler.main';

    /**
     * @param array<string, mixed> $expectedContext
     */
    protected function assertInfoLoggedOnce(string $message, array $expectedContext): void
    {
        $handler = self::getContainer()->get(self::LOG_HANDLER_ID);
        if (false === ($handler instanceof TestHandler)) {
            throw new LogicException(sprintf('Service "%s" is not a test log handler.', self::LOG_HANDLER_ID));
        }

        $contexts = [];
        foreach ($handler->getRecords() as $record) {
            if (Level::Info === $record->level && $message === $record->message) {
                $contexts[] = $record->context;
            }
        }

        self::assertSame([$expectedContext], $contexts);
    }
}
