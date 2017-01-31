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
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param CommandInterface $command
     * @return mixed|null
     */
    public function getCommandResponse(CommandInterface $command)
    {
        $event = new SymfonyEventDispatcherCommandEvent($command);
        $this->executedEvents[] = $event;
        $event->setExecutionStart();
        $this->dispatcher->dispatch(get_class($command), $event);
        $event->setExecutionStop();
        return $event->getResponse();
    }

    /**
     * @param CommandInterface $command
     */
    public function postCommand(CommandInterface $command)
    {
        foreach ($this->executedEvents as $executedEvent) {
            if ($command == $executedEvent->getCommand()) {
                return;
            }
        }
        $event = new SymfonyEventDispatcherCommandEvent($command);
        $event->setExecutionStart();
        $this->executedEvents[] = $event;
        $this->dispatcher->dispatch(get_class($command), $event);
        $event->setExecutionStop();
    }

    /**
     * @return array
     */
    public function getExecutedEvents(): array
    {
        return $this->executedEvents;
    }
}