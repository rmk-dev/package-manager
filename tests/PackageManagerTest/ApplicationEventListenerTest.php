<?php

namespace Rmk\PackageManagerTest;

use PHPUnit\Framework\TestCase;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\PackageManager\ApplicationEventListener;
use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
use Rmk\PackageManager\Exception\PackageManagerException;
use Rmk\PackageManager\PackageManager;

class ApplicationEventListenerTest extends TestCase
{

    public function testCatchException(): void
    {
        $event = new ApplicationInitEvent();
        $packageManager = $this->createStub(PackageManager::class);
        $exception = new PackageDoesNotExistsException( 'TestPackage does not exists');
        $packageManager->method('loadPackages')->willThrowException($exception);
        $listener = new ApplicationEventListener($packageManager);
        $listener->onApplicationInit($event);
        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame($exception, $event->getParam('exception'));
        $this->assertEquals($exception->getMessage(), $event->getStopReason());
    }
}
