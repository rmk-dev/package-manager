<?php

namespace Rmk\PackageManager\Events;

/**
 * Class DependencyCheckEvent
 *
 * @package Rmk\PackageManager\Events
 */
class EventListenersAddedEvent extends PackageEvent
{
    use PackageAwareTrait;

    public function getEventListeners(): array
    {
        return $this->getParam('event_listeners');
    }
}
