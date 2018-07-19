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
     * @var SymfonyEventDispatcherCommandEvent[]
     */
    protected $events = array();

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->events = array();
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
        $event = new SymfonyEventDispatcherCommandEvent($command, $userNotificationEnabled);
        $this->events[] = $event;
    }

    public function dispatchPostedCommand()
    {
        $dispatchedEvents = array();
        /* @var $dispatchedEvents SymfonyEventDispatcherCommandEvent[] */
        foreach ($this->events as $key => $event) {
            $alreadySend = false;
            foreach ($dispatchedEvents as $dispatchedEvent) {
                if ($dispatchedEvent->getCommand() == $event->getCommand()) {
                    $alreadySend = true;
                }
            }
            if (!$alreadySend) {
                $event->setExecutionStart();
                $this->dispatcher->dispatch(get_class($event->getCommand()), $event);
                $event->setExecutionStop();
                $dispatchedEvents[] = $event;
                $this->executedEvents[] = $event;
                unset($this->events[$key]);
            }
        }
    }

    /**
     * @return array
     */
    public function getExecutedEvents(): array
    {
        return $this->executedEvents;
    }
}
