<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit;

use PowerVending\LaravelApiQueryBuilder\ApiQueryServiceProvider;
use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class ApiQueryServiceProviderTest extends TestCase
{
    /** @test */
    public function validates_package_action_matrix()
    {
        $provider = new ApiQueryServiceProvider($this->app);

        $this->assertTrue(class_exists(SchemaController::class));
        $this->assertTrue($this->invokeIsValidPackageAction($provider, [SchemaController::class, 'show']));
        $this->assertFalse($this->invokeIsValidPackageAction($provider, [SchemaController::class, 'missingMethod']));
        $this->assertFalse($this->invokeIsValidPackageAction($provider, [\App\Http\Controllers\Controller::class, 'index']));
        $this->assertFalse($this->invokeIsValidPackageAction($provider, 'invalid'));
    }

    private function invokeIsValidPackageAction(ApiQueryServiceProvider $provider, mixed $action): bool
    {
        $method = new \ReflectionMethod($provider, 'isValidPackageAction');
        $method->setAccessible(true);

        return (bool) $method->invoke($provider, $action);
    }
}
