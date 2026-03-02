<?php

namespace Lapisense\WordPressClient\Tests\Unit;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('HOUR_IN_SECONDS')) {
            define('HOUR_IN_SECONDS', 3600);
        }

        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();

        parent::tearDown();
    }
}
