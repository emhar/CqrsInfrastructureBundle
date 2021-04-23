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
     * @var bool
     */
    protected $isInProgress;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->events = new \SplStack();
        $this->isInProgress = false;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandResponse(CommandInterface $command, bool $enableUserNotification = true, array $options = array())
    {
        $event = new SymfonyEventDispatcherCommandEvent($command, $enableUserNotification, false, $options, 0);
        $this->executedEvents[] = $event;
        $event->setExecutionStart();
        $this->dispatcher->dispatch(get_class($command), $event);
        $event->setExecutionStop();
        return $event->getResponse();
    }

    /**
     * {@inheritDoc}
     */
    public function postCommand(CommandInterface $command, bool $userNotificationEnabled = true, string $queue = self::DEFAULT_QUEUE, string $priority = self::PRIORITY_NORMAL, \DateTime $executeAfter = null, bool $isAsync = false, array $options = array(), int $retryCounter = 0)
    {
        foreach ($this->events as $event) {
            if ($command == $event->getCommand()) {
                return;
            }
        }
        $event = new SymfonyEventDispatcherCommandEvent($command, $userNotificationEnabled, $isAsync, $options, $retryCounter);
        $this->events->push($event);
    }

    public function dispatchPostedCommand(CqrsEventsCollectedEvent $cqrsEventsCollectedEvent)
    {
        if (!$this->isInProgress) {
            $this->isInProgress = true;
            while ($event = $this->getNextEvent()) {
                $event->setExecutionId($cqrsEventsCollectedEvent->getExecutionId());
                $event->setOptions(array_merge($event->getOptions(), $cqrsEventsCollectedEvent->getOptions()));
                $event->setExecutionStart();
                $this->dispatcher->dispatch(get_class($event->getCommand()), $event);
                $event->setExecutionStop();
                $this->executedEvents[] = $event;
                if ($event->getError()) {
                    $cqrsEventsCollectedEvent->addError($event->getError());
                }
            }
            $this->isInProgress = false;
        }
    }

    /**
     * @return SymfonyEventDispatcherCommandEvent|null
     */
    protected function getNextEvent()
    {
        if ($this->events->isEmpty()) {
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
