<?php

namespace Test\ValidPackage {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        public function getServices(): array
        {
            $this->services['ServiceName'] = new \stdClass();
            return parent::getServices();
        }
    }
}

namespace Test\Package {

    use Rmk\PackageManager\AbstractPackage;
    use Rmk\Router\Route;

    class Package extends AbstractPackage {
        protected string $version = '1.0.1';
        public function getRoutes(): array
        {
            $this->routes = [
                new Route('test', '/test-url', 'GET'),
            ];
            return parent::getRoutes(); // TODO: Change the autogenerated stub
        }
    }
}

namespace Test\Dependant {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected array $dependencies = [
            'Test\Package' => '^1.0.0',
        ];
    }
}

namespace Test\ComposerDependant {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected array $composerDependencies = [
            'phpunit/phpunit' => '^9.0.0'
        ];
    }
}

namespace Test\InvalidDependant {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected array $dependencies = [
            'Unknown\PackageName' => '^1.0.0',
        ];
    }
}

namespace Test\InvalidDependantVersion {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected array $dependencies = [
            'Test\Package' => '^8.5.0',
        ];
    }
}

namespace Test\InvalidComposerDependant {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected array $composerDependencies = [
            'unknown/package' => '^9.0.0'
        ];
    }
}

namespace Test\InvalidComposerVersion {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected array $composerDependencies = [
            'phpunit/phpunit' => '^12.0.0'
        ];
    }
}

namespace Test\NonExistingPackageClass {

    use Rmk\PackageManager\AbstractPackage;

    class PackageClass extends AbstractPackage {}
}

namespace Test\InvalidPackageClass {
    class Package {}
}

namespace Test\EventListenerProvider {

    use Rmk\PackageManager\AbstractPackage;
    use Rmk\PackageManager\Events\PackageLoadedEvent;
    use \RuntimeException;
    use Rmk\Application\Event\ApplicationInitEvent;


    class Package extends AbstractPackage
    {

        public function getEventListeners(): array
        {
            $this->eventListeners = [
                PackageLoadedEvent::class => [
                    function (PackageLoadedEvent $event) {
                        $exception = new RuntimeException('Test exception');
                        $event->setParam('exception', $exception);
                        $event->stopPropagation($exception->getMessage());
                    },
                ]
            ];
            return parent::getEventListeners();
        }
    }
}

namespace Rmk\PackageManagerTest {

    use PHPUnit\Framework\TestCase;
    use Psr\EventDispatcher\EventDispatcherInterface;
    use Psr\EventDispatcher\StoppableEventInterface;
    use Rmk\Application\Event\ApplicationInitEvent;
    use Rmk\Application\Factory\RouterServiceFactory;
    use Rmk\CallbackResolver\CallbackResolver;
    use Rmk\Container\Container;
    use Rmk\Container\ContainerInterface;
    use Rmk\Event\EventDispatcher;
    use Rmk\Event\ListenerProvider;
    use Rmk\PackageManager\Events\PackageLoadedEvent;
    use Rmk\PackageManager\Exception\ComposerPackageNotInstalledException;
    use Rmk\PackageManager\Exception\ComposerPackageVersionException;
    use Rmk\PackageManager\Exception\DependencyPackageNotExistsException;
    use Rmk\PackageManager\Exception\DependencyVersionException;
    use Rmk\PackageManager\Exception\InvalidPackageException;
    use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
    use Rmk\PackageManager\PackageManager;
    use Rmk\Router\Adapter\AltoRouterAdapter;
    use Rmk\Router\RouterService;
    use Rmk\ServiceContainer\ServiceContainer;
    use Rmk\ServiceContainer\ServiceContainerInterface;
    use RuntimeException;

    /**
     * Class PackageManagerTest
     *
     * @package Rmk\PackageManagerTest\PackageManagerTest
     */
    class PackageManagerTest extends TestCase
    {

        protected PackageManager $packageManager;
        /**
         * @var EventDispatcherInterface
         */
        private EventDispatcherInterface $eventDispatcher;
        /**
         * @var ContainerInterface
         */
        private ContainerInterface $packages;
        /**
         * @var ApplicationInitEvent
         */
        private ApplicationInitEvent $applicationEvent;
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject|ContainerInterface
         */
        private $config;

        private array $packageList = [
            'Test\ValidPackage',
            'Test\Package',
            'Test\Dependant',
            'Test\ComposerDependant',
        ];

        private bool $configHasPackages = true;

        protected function setUp(): void
        {
            $this->config = $this->getMockForAbstractClass(ContainerInterface::class);
            $this->config->method('has')->willReturnCallback(function($name) {
                return $this->configHasPackages;
            });
            $this->config->method('get')->willReturnCallback(function ($name) {
                if ($name === PackageManager::PACKAGE_CONFIG_KEY) {
                    return $this->packageList;
                }
            });
            $serviceContainer = $this->getMockForAbstractClass(ServiceContainerInterface::class);
            $serviceContainer->method('has')->willReturnCallback(function ($name) {
                return $name === ServiceContainer::CONFIG_KEY;
            });
            $serviceContainer->method('get')->willReturnCallback(function ($name) {
                if ($name === ServiceContainer::CONFIG_KEY) {
                    return $this->config;
                }

                return null;
            });
            $this->applicationEvent = new ApplicationInitEvent($this, [
                'service_container' => $serviceContainer,
                'router' => new RouterService(new AltoRouterAdapter(new \AltoRouter()))
            ]);
            $this->eventDispatcher = new EventDispatcher(new ListenerProvider(new CallbackResolver($serviceContainer)));
            $this->packages = new Container();
            $this->packageManager = new PackageManager($this->eventDispatcher, $this->packages);
            $this->packageManager->setApplicationInitEvent($this->applicationEvent);
        }

        public function testGetters(): void
        {
            $this->assertSame($this->packages, $this->packageManager->getPackages());
            $this->assertSame($this->applicationEvent, $this->packageManager->getApplicationInitEvent());
        }

        public function testLoadPackages(): void
        {
            $this->packageManager->loadPackages();
            $this->assertFalse($this->applicationEvent->isPropagationStopped());
        }

        public function testWithoutPackages(): void
        {
            $this->configHasPackages = false;
            $this->packageManager->loadPackages();
            $this->assertFalse($this->applicationEvent->isPropagationStopped());
        }

        public function testNonExistingPackageClass(): void
        {
            $this->packageList[] = 'Test\NonExistingPackageClass';
            $this->expectException(PackageDoesNotExistsException::class);
            $this->expectExceptionMessage('Test\NonExistingPackageClass does not exists');
            $this->packageManager->loadPackages();
//            $this->assertTrue($this->applicationEvent->isPropagationStopped());
//            $this->assertEquals('Test\NonExistingPackageClass does not exists', $this->applicationEvent->getStopReason());
//            $this->assertInstanceOf(PackageDoesNotExistsException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidPackageClass';
            $this->expectException(InvalidPackageException::class);
            $this->expectExceptionMessage('Test\InvalidPackageClass is not a valid package');
            $this->packageManager->loadPackages();
//            $this->assertTrue($this->applicationEvent->isPropagationStopped());
//            $this->assertEquals('Test\InvalidPackageClass is not a valid package', $this->applicationEvent->getStopReason());
//            $this->assertInstanceOf(InvalidPackageException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidDependantPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidDependant';
            $this->expectException(DependencyPackageNotExistsException::class);
            $this->expectExceptionMessageMatches('/ is required as dependency, but is not loaded$/');
            $this->packageManager->loadPackages();
//            $this->assertTrue($this->applicationEvent->isPropagationStopped());
//            $this->assertStringMatchesFormat('%s is required as dependency, but is not loaded', $this->applicationEvent->getStopReason());
//            $this->assertInstanceOf(DependencyPackageNotExistsException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidDependantVersionPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidDependantVersion';
            $this->expectException(DependencyVersionException::class);
            $this->expectExceptionMessageMatches('/is required in version constraint [\w\.\^\-]+, version [\w\.\^\-]+ is installed$/');
            $this->packageManager->loadPackages();
//            $this->assertTrue($this->applicationEvent->isPropagationStopped());
//            $this->assertStringMatchesFormat('%s is required in version constraint %s, version %s is installed', $this->applicationEvent->getStopReason());
//            $this->assertInstanceOf(DependencyVersionException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidComposerDependantPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidComposerDependant';
            $this->expectException(ComposerPackageNotInstalledException::class);
            $this->expectExceptionMessageMatches('/^Composer package [\w\-\.\/]+ is required, but not installed. Try running \'composer require [\w\-\.\/]+\'$/');
            $this->packageManager->loadPackages();
//            $this->assertTrue($this->applicationEvent->isPropagationStopped());
//            $this->assertStringMatchesFormat('Composer package %s is required, but not installed. Try running \'composer require %s\'', $this->applicationEvent->getStopReason());
//            $this->assertInstanceOf(ComposerPackageNotInstalledException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidComposerDependantVersionPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidComposerVersion';
            $this->expectException(ComposerPackageVersionException::class);
            $this->expectExceptionMessageMatches('/^Composer package [\w\-\.\/]+ in version [\w\^\-\.\/]+ is required, but version [\w\^\-\.\/]+ is installed$/');
            $this->packageManager->loadPackages();
//            $this->assertTrue($this->applicationEvent->isPropagationStopped());
//            $this->assertStringMatchesFormat('Composer package %s in version %s is required, but version %s is installed', $this->applicationEvent->getStopReason());
//            $this->assertInstanceOf(ComposerPackageVersionException::class, $this->applicationEvent->getParam('exception'));

        }

        public function testStoppedEvent(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Test exception');
            $this->packageList[] = 'Test\EventListenerProvider';
            $this->packageManager->loadPackages();
        }
    }
}