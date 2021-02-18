<?php

namespace Rmk\PackageManager;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\Container\ContainerInterface;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\EventInterface;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Events\AfterPackagesLoadedEvent;
use Rmk\PackageManager\Events\BeforePackagesLoadEvent;
use Rmk\PackageManager\Exception\InvalidCacheKey;
use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
use Rmk\ServiceContainer\Exception\ServiceNotFoundException;
use Rmk\ServiceContainer\ServiceContainer;
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
     * The name of the configuration about the package manager cache
     */
    public const PACKAGES_CACHE_CONFIG = 'packages_cache_config';

    /**
     * The key for the cached configuration
     */
    public const DEFAULT_CACHE_KEY = 'package_manager_config';

    /**
     * The config key of package list
     */
    public const PACKAGE_CONFIG_KEY = 'packages';

    /**
     * Default living time for config cache in seconds - 1 hour
     */
    public const DEFAULT_CACHE_TTL = 3600;

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
     * The object that configures packages
     *
     * @var PackageConfigurator
     */
    protected PackageConfigurator $packageConfigurator;

    /**
     * The object that creates packages
     *
     * @var PackageInitiator
     */
    protected PackageInitiator $packageInitiator;

    /**
     * Object that validates the package dependencies
     *
     * @var PackageDependencyChecker
     */
    protected PackageDependencyChecker $packageDependencyChecker;

    /**
     * The cache adapter
     *
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * The key for cached config
     *
     * @var string
     */
    private string $cacheKey;

    /**
     * Time-to-live for the cached config
     *
     * @var int
     */
    private int $cacheTtl = 3600;

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
     * @param ApplicationInitEvent $applicationInitEvent
     *
     * @throws Throwable
     */
    public function loadPackages(ApplicationInitEvent $applicationInitEvent): void
    {
        $this->setApplicationInitEvent($applicationInitEvent);
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
        $this->instantiateCache();
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
        try {
            $cachedItem = $this->cache->get($this->cacheKey);
            if (is_array($cachedItem)) {
                $this->packageConfigurator->setMergedConfig($cachedItem);
            } else {
                foreach ($this->packages as $package) {
                    $this->packageConfigurator->configure($package);
                }
                $this->cache->set($this->cacheKey, $this->packageConfigurator->getMergedConfig(), $this->cacheTtl);
            }
        } catch (InvalidArgumentException $e) {
            $this->cacheKey = self::DEFAULT_CACHE_KEY;
            $this->configurePackages();
        }
    }

    /**
     * Creates the cache adapter instance
     */
    protected function instantiateCache(): void
    {
        $serviceContainer = $this->getApplicationInitEvent()->getServiceContainer();
        /** @var ContainerInterface $config */
        $config = $serviceContainer->get(ServiceContainer::CONFIG_KEY);
        $cacheConfig = [];
        if ($config->has(self::PACKAGES_CACHE_CONFIG)) {
            $cacheConfig = (array) $config->get(self::PACKAGES_CACHE_CONFIG);
        }
        $this->cacheKey = $cacheConfig['key'] ?? self::DEFAULT_CACHE_KEY;
        $this->cacheTtl = $cacheConfig['ttl'] ?? self::DEFAULT_CACHE_TTL;
        if (array_key_exists('adapter', $cacheConfig) && $serviceContainer->has($cacheConfig['adapter'])) {
            $this->cache = $serviceContainer->get($cacheConfig['adapter']);
        } else  {
            $this->cache = new DefaultCacheAdapter();
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
