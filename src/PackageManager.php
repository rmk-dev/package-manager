<?php

namespace Rmk\PackageManager;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\Container\ContainerInterface;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\EventInterface;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Events\AfterPackagesLoadedEvent;
use Rmk\PackageManager\Events\BeforePackagesLoadEvent;
use Rmk\PackageManager\Events\ComposerDependencyCheckEvent;
use Rmk\PackageManager\Events\DependencyCheckEvent;
use Rmk\PackageManager\Exception\ComposerPackageNotInstalledException;
use Rmk\PackageManager\Exception\ComposerPackageVersionException;
use Rmk\PackageManager\Exception\DependencyPackageNotExistsException;
use Rmk\PackageManager\Exception\DependencyVersionException;
use Rmk\PackageManager\Exception\InvalidPackageException;
use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
use Rmk\ServiceContainer\ServiceContainer;
use Rmk\ServiceContainer\ServiceContainerInterface;
use Throwable;

/**
 * Class PackageManager
 *
 * @package Rmk\PackageManager
 */
class PackageManager implements EventDispatcherAwareInterface
{

    use EventDispatcherAwareTrait;

    use PackageEventDispatcherTrait;

    /**
     * The config key of package list
     */
    public const PACKAGE_CONFIG_KEY = 'packages';

    /**
     * The main application event the manager is attached to
     *
     * @var ApplicationInitEvent
     */
    protected ApplicationInitEvent $applicationInitEvent;

    /**
     * The list with package names that should be loaded
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $packages;

    /**
     * A list with names and versions of the loaded packages
     *
     * @var array|string[]
     */
    protected array $packageVersions = [];

    /**
     * @var PackageConfigurator
     */
    protected PackageConfigurator $packageConfigurator;

    /**
     * @var PackageInitiator
     */
    protected PackageInitiator $packageInitiator;

    /**
     * @var PackageDependencyChecker
     */
    protected PackageDependencyChecker $packageDependencyChecker;

    /**
     * PackageManager constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param ContainerInterface $packages
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, ContainerInterface $packages)
    {
        $this->setEventDispatcher($eventDispatcher);
        $this->setPackages($packages);
    }

    /**
     * Loads application packages
     *
     * @throws Throwable
     */
    public function loadPackages(): void
    {
        $this->packageInitiator = new PackageInitiator($this);
        $this->packageDependencyChecker = new PackageDependencyChecker($this);
        $this->packageConfigurator = new PackageConfigurator($this);
        /** @var ContainerInterface $config */
        $config = $this->applicationInitEvent->getServiceContainer()->get(ServiceContainer::CONFIG_KEY);
        if (!$config->has(self::PACKAGE_CONFIG_KEY)) {
            return;
        }
        $packageList = $config->get(self::PACKAGE_CONFIG_KEY);
        $this->dispatchPackageEvent(new BeforePackagesLoadEvent($this, [
            'config' => $config,
            'package_list' => $packageList,
            EventInterface::PARENT_EVENT => $this->applicationInitEvent,
        ]));
        $this->instantiatePackages($packageList);
        $this->checkDependencies();
        $this->configurePackages();
        $this->getApplicationInitEvent()->getServiceContainer()->init($this->packageConfigurator->getMergedConfig());
        $this->dispatchPackageEvent(new AfterPackagesLoadedEvent($this, [
            'packages' => $this->packages,
            EventInterface::PARENT_EVENT => $this->applicationInitEvent,
        ]));
        $this->applicationInitEvent->setParam('package_manager', $this);
    }

    /**
     * @param string $packageName
     *
     * @return bool
     */
    public function hasPackage(string $packageName): bool
    {
        return $this->packages->has($packageName);
    }

    /**
     * @param string $packageName
     *
     * @return PackageInterface
     */
    public function getPackage(string $packageName): PackageInterface
    {
        try {
            return $this->packages->get($packageName);
        } catch (Throwable $e) {
            throw new PackageDoesNotExistsException($packageName.' does not exists', 1, $e);
        }
    }

    /**
     * Creates objects for every package
     *
     * @param array $packageList
     */
    protected function instantiatePackages(array $packageList): void
    {
        foreach ($packageList as $packageName) {
            $package = $this->packageInitiator->instantiatePackage($packageName);
            $this->packages->add($package, $packageName);
            $this->packageVersions[$packageName] = $package->getVersion();
        }
    }

    /**
     * Checks all packages dependencies
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function checkDependencies(): void
    {
        foreach ($this->packages as $package) {
            $this->packageDependencyChecker->checkDependencies($package);
        }
    }

    /**
     * Initialize the packages
     *
     * @throws Throwable
     */
    protected function configurePackages(): void
    {
        foreach ($this->packages as $package) {
            $this->packageConfigurator->configure($package);
        }
    }

    /**
     * @return ContainerInterface
     */
    public function getPackages(): ContainerInterface
    {
        return $this->packages;
    }

    /**
     * @param ContainerInterface $packages
     */
    public function setPackages(ContainerInterface $packages): void
    {
        $this->packages = $packages;
    }

    /**
     * @return ApplicationInitEvent
     */
    public function getApplicationInitEvent(): ApplicationInitEvent
    {
        return $this->applicationInitEvent;
    }

    /**
     * @param ApplicationInitEvent $applicationInitEvent
     */
    public function setApplicationInitEvent(ApplicationInitEvent $applicationInitEvent): void
    {
        $this->applicationInitEvent = $applicationInitEvent;
    }
}
