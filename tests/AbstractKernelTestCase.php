<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;

abstract class AbstractKernelTestCase extends AnzuKernelTestCase
{
    use DatabaseTestTrait;
}
