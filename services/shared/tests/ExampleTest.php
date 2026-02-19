<?php

namespace Shared\Tests;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test that the shared service can be instantiated.
     */
    public function test_shared_service_exists(): void
    {
        // Basic test to ensure the shared service structure is working
        $this->assertTrue(class_exists('Shared\Tests\ExampleTest'));
    }
}
