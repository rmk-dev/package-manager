<?php

namespace Rmk\PackageManager;

use Rmk\Application\Event\ApplicationInitEvent;

/**
 * Class AbstractPackage
 * @package Rmk\PackageManager
 */
abstract class AbstractPackage implements
    DependantPackageInterface,
    ConfigProviderInterface,
    ServiceProviderInterface,
    RoutesProviderInterface,
    EventListenersProviderInterface
{

    /**
     * The package's version
     *
     * @var string
     */
    protected string $version = 'v1.0.0';

    /**
     * The package name. It would be set automatically by the package manager
     * @var string
     */
    protected string $name = '';

    /**
     * List of packages the current package depends on
     *
     * @var array
     */
    protected array $dependencies = [];

    /**
     * List of composer packages the current package depends on
     *
     * @var array
     */
    protected array $composerDependencies = [];

    /**
     * List with package configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * List with services
     *
     * @var array
     */
    protected array $services = [];

    /**
     * List with event listeners
     *
     * @var array
     */
    protected array $eventListeners = [];

    /**
     * List with routes
     *
     * @var array
     */
    protected array $routes;

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param ApplicationInitEvent $event
     */
    public function init(ApplicationInitEvent $event): void
    {
        // Implement init() method in the subclasses
    }

    /**
     * @return array
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return array
     */
    public function getComposerDependencies(): array
    {
        return $this->composerDependencies;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return array
     */
    public function getEventListeners(): array
    {
        return $this->eventListeners;
    }

    /**
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
