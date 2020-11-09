<?php

namespace Test\ValidPackage {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {}
}

namespace Test\Package {

    use Rmk\PackageManager\AbstractPackage;

    class Package extends AbstractPackage {
        protected string $version = '1.0.1';
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

namespace Rmk\PackageManagerTest {

    use PHPUnit\Framework\TestCase;
    use Psr\EventDispatcher\EventDispatcherInterface;
    use Psr\EventDispatcher\StoppableEventInterface;
    use Rmk\Application\Event\ApplicationInitEvent;
    use Rmk\Container\Container;
    use Rmk\Container\ContainerInterface;
    use Rmk\PackageManager\Exception\ComposerPackageNotInstalledException;
    use Rmk\PackageManager\Exception\ComposerPackageVersionException;
    use Rmk\PackageManager\Exception\DependencyPackageNotExistsException;
    use Rmk\PackageManager\Exception\DependencyVersionException;
    use Rmk\PackageManager\Exception\InvalidPackageException;
    use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
    use Rmk\PackageManager\PackageManager;
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
            ]);
            $this->eventDispatcher = $this->getMockForAbstractClass(EventDispatcherInterface::class);
            $this->packages = new Container();
            $this->packageManager = new PackageManager($this->eventDispatcher, $this->packages);
        }

        public function testGetters(): void
        {
            $this->assertSame($this->packages, $this->packageManager->getPackages());
        }

        public function testLoadPackages(): void
        {
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertFalse($this->applicationEvent->isPropagationStopped());
        }

        public function testWithoutPackages(): void
        {
            $this->configHasPackages = false;
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertFalse($this->applicationEvent->isPropagationStopped());
        }

        public function testNonExistingPackageClass(): void
        {
            $this->packageList[] = 'Test\NonExistingPackageClass';
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertTrue($this->applicationEvent->isPropagationStopped());
            $this->assertEquals('Test\NonExistingPackageClass does not exists', $this->applicationEvent->getStopReason());
            $this->assertInstanceOf(PackageDoesNotExistsException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidPackageClass';
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertTrue($this->applicationEvent->isPropagationStopped());
            $this->assertEquals('Test\InvalidPackageClass is not a valid package', $this->applicationEvent->getStopReason());
            $this->assertInstanceOf(InvalidPackageException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidDependantPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidDependant';
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertTrue($this->applicationEvent->isPropagationStopped());
            $this->assertStringMatchesFormat('%s is required as dependency, but is not loaded', $this->applicationEvent->getStopReason());
            $this->assertInstanceOf(DependencyPackageNotExistsException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidDependantVersionPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidDependantVersion';
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertTrue($this->applicationEvent->isPropagationStopped());
            $this->assertStringMatchesFormat('%s is required in version constraint %s, version %s is installed', $this->applicationEvent->getStopReason());
            $this->assertInstanceOf(DependencyVersionException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidComposerDependantPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidComposerDependant';
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertTrue($this->applicationEvent->isPropagationStopped());
            $this->assertStringMatchesFormat('Composer package %s is required, but not installed. Try running \'composer require %s\'', $this->applicationEvent->getStopReason());
            $this->assertInstanceOf(ComposerPackageNotInstalledException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testInvalidComposerDependantVersionPackageClass(): void
        {
            $this->packageList[] = 'Test\InvalidComposerVersion';
            $this->packageManager->onApplicationInit($this->applicationEvent);
            $this->assertTrue($this->applicationEvent->isPropagationStopped());
            $this->assertStringMatchesFormat('Composer package %s in version %s is required, but version %s is installed', $this->applicationEvent->getStopReason());
            $this->assertInstanceOf(ComposerPackageVersionException::class, $this->applicationEvent->getParam('exception'));
        }

        public function testStoppedEvent(): void
        {
            $exception = new RuntimeException('Test exception');
            $this->eventDispatcher->method('dispatch')->willReturnCallback(function(StoppableEventInterface $event) use ($exception) {
                $event->stopPropagation($exception->getMessage());
                $event->setParam('exception', $exception);
            });
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Test exception');
            $this->packageManager->onApplicationInit($this->applicationEvent);
        }
    }
}
