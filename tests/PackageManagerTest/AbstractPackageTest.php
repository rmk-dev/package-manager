<?php

namespace Rmk\PackageManagerTest;

use PHPUnit\Framework\TestCase;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\PackageManager\AbstractPackage;

class AbstractPackageTest extends TestCase
{

    public function testGetters(): void
    {
        $package = new class extends AbstractPackage {
            protected string $version = 'v1.1.0';
            protected array $dependencies = [
                'Rmk\SomePackage' => '1.0',
            ];
            protected array $composerDependencies = [
                'rmk/application' => '1.0',
            ];
            protected array $config = [
                'some_key' => 'value1'
            ];
            protected array $eventListeners = [
                ApplicationInitEvent::class => [
                    'some_callback_listener',
                ]
            ];
            protected array $routes = [
                'home' => [
                    'url' => '/',
                ]
            ];
            protected array $services = [
                'factories' => [
                    'SomeServiceFactory',
                ]
            ];
        };
        $this->assertEquals('v1.1.0', $package->getVersion());
        $this->assertEquals(['Rmk\SomePackage' => '1.0'], $package->getDependencies());
        $this->assertEquals(['rmk/application' => '1.0'], $package->getComposerDependencies());
        $this->assertEquals(['some_key' => 'value1'], $package->getConfig());
        $this->assertEquals([ApplicationInitEvent::class => ['some_callback_listener']], $package->getEventListeners());
        $this->assertEquals(['home' => ['url' => '/']], $package->getRoutes());
        $this->assertEquals(['factories' => ['SomeServiceFactory']], $package->getServices());
        $this->assertNull($package->init(new ApplicationInitEvent($this)));
    }
}