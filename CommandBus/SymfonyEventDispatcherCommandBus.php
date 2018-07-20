<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\CommandBus;

use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Emhar\CqrsInfrastructure\CommandBus\CommandBusInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SymfonyEventDispatcherCommandBus implements CommandBusInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var SymfonyEventDispatcherCommandEvent[]
     */
    protected $executedEvents = array();

    /**
     * @var \SplStack
     */
    protected $events;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->events = new \SplStack();
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandResponse(CommandInterface $command, bool $enableUserNotification = true)
    {
        $event = new SymfonyEventDispatcherCommandEvent($command, $enableUserNotification);
        $this->executedEvents[] = $event;
        $event->setExecutionStart();
        $this->dispatcher->dispatch(get_class($command), $event);
        $event->setExecutionStop();
        return $event->getResponse();
    }

    /**
     * {@inheritDoc}
     */
    public function postCommand(CommandInterface $command, bool $userNotificationEnabled = true)
    {
        foreach ($this->events as $event) {
            if ($command == $event->getCommand()) {
                return;
            }
        }
        $event = new SymfonyEventDispatcherCommandEvent($command, $userNotificationEnabled);
        $this->events->push($event);
    }

    public function dispatchPostedCommand()
    {
        while ($event = $this->getNextEvent()){
            $event->setExecutionStart();
            $this->dispatcher->dispatch(get_class($event->getCommand()), $event);
            $event->setExecutionStop();
            $this->executedEvents[] = $event;
        }
    }

    /**
     * @return SymfonyEventDispatcherCommandEvent|null
     */
    protected function getNextEvent()
    {
        if($this->events->isEmpty()){
            return null;
        }
        return $this->events->shift();
    }

    /**
     * @return array
     */
    public function getExecutedEvents(): array
    {
        return $this->executedEvents;
    }
}
