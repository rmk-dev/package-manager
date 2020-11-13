<?php

namespace Rmk\PackageManager;

use Psr\EventDispatcher\ListenerProviderInterface;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\Event\EventDispatcher;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\EventInterface;
use Rmk\Event\ListenerProvider;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Events\ConfigMergedEvent;
use Rmk\PackageManager\Events\EventListenersAddedEvent;
use Rmk\PackageManager\Events\PackageLoadedEvent;
use Rmk\PackageManager\Events\ServicesAddedEvent;
use Throwable;

/**
 * Class PackageConfigurator
 *
 * @package Rmk\PackageManager
 */
class PackageConfigurator implements EventDispatcherAwareInterface
{

    use EventDispatcherAwareTrait;

    use PackageEventDispatcherTrait;

    /**
     * The main application event the manager is attached to
     *
     * @var ApplicationInitEvent
     */
    protected ApplicationInitEvent $applicationInitEvent;

    /**
     * Merged configurations from all packages
     *
     * @var array
     */
    protected array $mergedConfig = [];

    /**
     * @var PackageManager
     */
    protected PackageManager $packageManager;

    protected ListenerProviderInterface $listenerProvider;

    /**
     * PackageConfigurator constructor.
     *
     * @param PackageManager $packageManager
     */
    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
        $this->setEventDispatcher($packageManager->getEventDispatcher());
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getEventDispatcher();
        if ($eventDispatcher instanceof EventDispatcher) {
            /** @var ListenerProvider $listenerProvider */
            $this->listenerProvider = $eventDispatcher->getListenerProvider();
        }
    }

    /**
     * Initialize the packages
     *
     * @param PackageInterface $package
     *
     * @throws Throwable
     */
    public function configure(PackageInterface $package): void
    {
        $this->loadConfig($package);
        $this->loadServices($package);
        $this->loadEventListeners($package);
        $this->loadRoutes($package);
        $package->init($this->packageManager->getApplicationInitEvent());
        $this->dispatchPackageEvent(new PackageLoadedEvent($this->packageManager, [
            'package' => $package,
            EventInterface::PARENT_EVENT => $this->packageManager->getApplicationInitEvent()
        ]));
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
            $this->dispatchPackageEvent(new ConfigMergedEvent($this->packageManager, [
                'package' => $package,
                'config' => $config,
                'merged_config' => $this->mergedConfig,
                EventInterface::PARENT_EVENT => $this->packageManager->getApplicationInitEvent()
            ]));
        }
    }

    /**
     * Load services if any and adds them to the service container
     *
     * @param PackageInterface $package
     *
     * @throws Throwable
     */
    protected function loadServices(PackageInterface $package): void
    {
        if ($package instanceof ServiceProviderInterface) {
            $serviceContainer = $this->packageManager->getApplicationInitEvent()->getServiceContainer();
            foreach ($package->getServices() as $serviceName => $service) {
                $serviceContainer->add($service, $serviceName);
            }
            $this->dispatchPackageEvent(new ServicesAddedEvent($this->packageManager, [
                'package' => $package,
                EventInterface::PARENT_EVENT => $this->packageManager->getApplicationInitEvent()
            ]));
        }
    }

    /**
     * Loads event listeners
     *
     * @param PackageInterface $package
     *
     * @throws Throwable
     */
    protected function loadEventListeners(PackageInterface $package): void
    {
        if (!($this->listenerProvider instanceof ListenerProvider)) {
            return; // @codeCoverageIgnore
        }
        if ($package instanceof EventListenersProviderInterface) {
            $eventListeners = $package->getEventListeners();
            foreach ($eventListeners as $eventName => $listeners) {
                $this->configEventListeners($listeners, $eventName);
            }
            $this->dispatchPackageEvent(new EventListenersAddedEvent($this->packageManager, [
                'package' => $package,
                'event_listeners' => $eventListeners,
            ]));
        }
    }

    protected function configEventListeners(iterable $listeners, string $eventName): void
    {
        foreach ($listeners as $listener) {
            $priority = 0;
            if (is_array($listener)) {
                $listener = array_slice($listener, 0, 3);
                if (count($listener) > 2) {
                    $priority = (int) array_pop($listener);
                }
                if (!is_callable($listener)) {
                    $priority = (int) array_pop($listener);
                    $listener = array_pop($listener);
                }
            }
            $this->listenerProvider->addEventListener($eventName, $listener, $priority);
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
            $router = $this->packageManager->getApplicationInitEvent()->getRouter();
            foreach ($package->getRoutes() as $route) {
                $router->getRouterAdapter()->add($route);
            }
        }
    }

    /**
     * @return array
     */
    public function getMergedConfig(): array
    {
        return $this->mergedConfig;
    }
}
