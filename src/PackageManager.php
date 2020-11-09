<?php

namespace Rmk\PackageManager;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Ds\Queue;
use Psr\EventDispatcher\EventDispatcherInterface;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\Container\ContainerInterface;
use Rmk\Event\EventDispatcher;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\EventInterface;
use Rmk\Event\ListenerProvider;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Events\BeforePackagesLoadEvent;
use Rmk\PackageManager\Events\ComposerDependencyCheckEvent;
use Rmk\PackageManager\Events\ConfigMergedEvent;
use Rmk\PackageManager\Events\DependencyCheckEvent;
use Rmk\PackageManager\Events\PackageEvent;
use Rmk\PackageManager\Events\PackageLoadedEvent;
use Rmk\PackageManager\Events\ServicesAddedEvent;
use Rmk\PackageManager\Exception\ComposerPackageNotInstalledException;
use Rmk\PackageManager\Exception\ComposerPackageVersionException;
use Rmk\PackageManager\Exception\DependencyPackageNotExistsException;
use Rmk\PackageManager\Exception\DependencyVersionException;
use Rmk\PackageManager\Exception\InvalidPackageException;
use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
use Rmk\PackageManager\Exception\PackageManagerException;
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
     * Version parser for composer packages
     *
     * @var VersionParser
     */
    protected VersionParser $versionParser;

    /**
     * The application service container
     *
     * @var ServiceContainerInterface
     */
    protected ServiceContainerInterface $serviceContainer;

    /**
     * Merged configurations from all packages
     *
     * @var array
     */
    protected array $mergedConfig = [];

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
     * The event handler
     *
     * @param ApplicationInitEvent $event
     *
     * @throws Throwable
     */
    public function onApplicationInit(ApplicationInitEvent $event): void
    {
        $this->applicationInitEvent = $event;
        $this->loadPackages();
    }

    /**
     * Loads application packages
     *
     * @throws Throwable
     */
    public function loadPackages(): void
    {
        $this->serviceContainer = $this->applicationInitEvent->getServiceContainer();
        /** @var ContainerInterface $config */
        $config = $this->serviceContainer->get(ServiceContainer::CONFIG_KEY);
        $this->mergedConfig = $config->toArray();
        if (!$config->has(self::PACKAGE_CONFIG_KEY)) {
            return;
        }
        $packageList = $config->get(self::PACKAGE_CONFIG_KEY);
        try {
            $this->dispatchPackageEvent(new BeforePackagesLoadEvent($this, [
                'config' => $config,
                'package_list' => $packageList,
                EventInterface::PARENT_EVENT => $this->applicationInitEvent,
            ]));
            $this->instantiatePackages($packageList);
            $this->versionParser = new VersionParser();
            $this->checkDependencies();
            $this->initPackages();
        } catch (PackageManagerException $exception) {
            $this->applicationInitEvent->setParam('exception', $exception);
            $this->applicationInitEvent->stopPropagation($exception->getMessage());
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
            $packageClass = $packageName . '\\Package';
            if (!class_exists($packageClass)) {
                throw new PackageDoesNotExistsException($packageName . ' does not exists');
            }
            $package = new $packageClass();
            if (!($package instanceof PackageInterface)) {
                throw new InvalidPackageException($packageName . ' is not a valid package');
            }
            if (method_exists($package, 'setName')) {
                $package->setName($packageName);
            }
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
            /** @var PackageInterface $package */
            if ($package instanceof DependantPackageInterface) {
                // Check whether the required composer packages are installed
                $this->checkComposerDependencies($package);
                $this->dispatchPackageEvent(new ComposerDependencyCheckEvent($this, [
                    'package' => $package,
                    'composer_dependencies' => $package->getComposerDependencies(),
                ]));

                // Check whether the required application packages are installed
                $this->checkPackageDependencies($package);
                $this->dispatchPackageEvent(new DependencyCheckEvent($this, [
                    'package' => $package,
                    'dependencies' => $package->getDependencies(),
                ]));
            }
        }
    }

    /**
     * Checks whether required packages are installed with the required versions
     *
     * @param DependantPackageInterface $package
     */
    protected function checkPackageDependencies(DependantPackageInterface $package): void
    {
        $dependencies = $package->getDependencies();
        $dependencyMissingErr = '%s is required as dependency, but is not loaded';
        $versionErrMessage = '%s is required in version constraint %s, version %s is installed';
        foreach ($dependencies as $packageName => $packageVersion) {
            if (!$this->packages->has($packageName)) {
                throw new DependencyPackageNotExistsException(sprintf($dependencyMissingErr, $packageName));
            }
            /** @var PackageInterface $dependency */
            $dependency = $this->packages->get($packageName);
            $dependencyVersion = $dependency->getVersion();
            if (!Semver::satisfies($dependencyVersion, $packageVersion)) {
                throw new DependencyVersionException(sprintf($versionErrMessage, $packageName, $packageVersion, $dependencyVersion));
            }
        }
    }

    /**
     * Checks whether composer packages are installed in the required versions
     *
     * @param DependantPackageInterface $package
     */
    protected function checkComposerDependencies(DependantPackageInterface $package): void
    {
        $composerDependencies = $package->getComposerDependencies();
        $composerPackageErr = 'Composer package %s is required, but not installed. Try running \'composer require %s\'';
        $composerVersionErr = 'Composer package %s in version %s is required, but version %s is installed';
        foreach ($composerDependencies as $packageName => $packageVersion) {
            if (!InstalledVersions::isInstalled($packageName)) {
                throw new ComposerPackageNotInstalledException(sprintf($composerPackageErr, $packageName, $packageName));
            }
            if (!InstalledVersions::satisfies($this->versionParser, $packageName, $packageVersion)) {
                $installedVersion = InstalledVersions::getVersion($packageName);
                throw new ComposerPackageVersionException(sprintf($composerVersionErr, $packageName, $packageVersion, $installedVersion));
            }
        }
    }

    /**
     * Initialize the packages
     *
     * @throws Throwable
     */
    protected function initPackages(): void
    {
        foreach ($this->packages as $package) {
            /** @var PackageInterface $package */
            $this->loadConfig($package);
            $this->loadConfig($package);
            $this->loadServices($package);
            $this->loadEventListeners($package);
            $this->loadRoutes($package);
            $package->init($this->applicationInitEvent);
            $this->dispatchPackageEvent(new PackageLoadedEvent($this, [
                'package' => $package,
                EventInterface::PARENT_EVENT => $this->applicationInitEvent
            ]));
        }
    }

    /**
     * Loads package config and adds it to the merged one
     *
     * @param PackageInterface $package
     * @throws Throwable
     */
    protected function loadConfig(PackageInterface $package): void
    {
        if ($package instanceof ConfigProviderInterface) {
            $config = $package->getConfig();
            $this->mergedConfig = array_replace_recursive($this->mergedConfig, $config);
            $this->dispatchPackageEvent(new ConfigMergedEvent($this, [
                'package' => $package,
                'config' => $config,
                'merged_config' => $this->mergedConfig,
            ]));
        }
    }

    /**
     * Load services if any and adds them to the service container
     *
     * @param PackageInterface $package
     * @throws Throwable
     */
    protected function loadServices(PackageInterface $package): void
    {
        if ($package instanceof ServiceProviderInterface) {
            foreach ($package->getServices() as $serviceName => $service) {
                $this->serviceContainer->add($service, $serviceName);
            }
            $this->dispatchPackageEvent(new ServicesAddedEvent($this, [
                'package' => $package,
                'services' => $this->serviceContainer->toArray(),
            ]));
        }
    }

    /**
     * Loads event listeners
     *
     * @param PackageInterface $package
     */
    protected function loadEventListeners(PackageInterface $package): void
    {
        if ($package instanceof EventListenersProviderInterface) {
            /** @var EventDispatcher $eventDispatcher */
            $eventDispatcher = $this->getEventDispatcher();
            if (!($eventDispatcher instanceof EventDispatcher)) {
                return; // @codeCoverageIgnore
            }
            /** @var ListenerProvider $listenerProvider */
            $listenerProvider = $eventDispatcher->getListenerProvider();
            if (!($listenerProvider instanceof ListenerProvider)) {
                return; // @codeCoverageIgnore
            }
            foreach ($package->getEventListeners() as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    // TODO Check for priorities!
                    $listenerProvider->addEventListener($eventName, $listener);
                }
            }
        }
    }

    /**
     * Load routes
     *
     * @param PackageInterface $package
     */
    protected function loadRoutes(PackageInterface $package): void
    {
        if ($package instanceof RoutesProviderInterface) {
            $router = $this->applicationInitEvent->getRouter();
            foreach ($package->getRoutes() as $route) {
                $router->getRouterAdapter()->add($route);
            }
        }
    }

    /**
     * Dispatch package event and checks whether it is stopped with exception
     *
     * @param PackageEvent $event
     *
     * @throws Throwable
     */
    protected function dispatchPackageEvent(PackageEvent $event): void
    {
        $this->getEventDispatcher()->dispatch($event);
        if ($event->isPropagationStopped()) {
            $exception = $event->getParam('exception');
            if ($exception && $exception instanceof Throwable) {
                throw $exception;
            }
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
}