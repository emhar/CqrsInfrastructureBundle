<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\CommandHandler;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Emhar\CqrsInfrastructure\CommandHandler\AbstractCommandHandler;
use Emhar\CqrsInfrastructure\CommandHandler\AbstractInfrastructureExceptionListeningCommandHandler;
use Emhar\CqrsInfrastructure\Event\EventContainerInterface;
use Emhar\CqrsInfrastructureBundle\CommandBus\SymfonyEventDispatcherCommandEvent;
use Emhar\CqrsInfrastructureBundle\Event\SymfonyEventDispatcherEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CommandHandlerAdapter
{
    /**
     * @var AbstractCommandHandler
     */
    protected $innerService;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ManagerRegistry
     */
    protected $doctrineRegistry;

    /**
     * @var EventContainerInterface
     */
    protected $eventCollector;

    /**
     * @param ManagerRegistry $doctrineRegistry
     * @param EventContainerInterface $eventCollector
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(ManagerRegistry $doctrineRegistry, EventContainerInterface $eventCollector, EventDispatcherInterface $eventDispatcher)
    {
        $this->doctrineRegistry = $doctrineRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventCollector = $eventCollector;
    }

    /**
     * @param AbstractCommandHandler $innerService
     */
    public function setInnerService(AbstractCommandHandler $innerService)
    {
        $this->innerService = $innerService;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        $commandEvent = null;
        $command = null;
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof SymfonyEventDispatcherCommandEvent) {
                $commandEvent = $argument;
                $command = $argument->getCommand();
                $arguments[$key] = $command;
                $this->innerService->setUserNotificationEnabled($commandEvent->isUserNotificationEnabled());
            }
        }
        $em = $this->doctrineRegistry->getManager();
        /* @var $em EntityManager */
        $em->beginTransaction();
        try {
            $result = call_user_func_array(array($this->innerService, $name), $arguments);
            if ($commandEvent) {
                $commandEvent->setResponse($result);
            }
            $this->doctrineRegistry->getManager()->flush();
            $events = $this->eventCollector->getEvents();
            foreach ($events as $event) {
                $event->setUserNotificationEnabled($commandEvent ? $commandEvent->isUserNotificationEnabled() : true);
                $this->eventDispatcher->dispatch(get_class($event), new SymfonyEventDispatcherEvent($event));
            }
            $this->doctrineRegistry->getManager()->flush();
            $em->commit();
            $this->eventDispatcher->dispatch('cqrs-event-collected');
            $this->doctrineRegistry->getManager()->flush();
        } catch (\Exception $e) {
            while ($em->getConnection()->getTransactionNestingLevel() > 0) {
                $em->getConnection()->rollback();
            }
            $em->close();
            if ($this->innerService instanceof AbstractInfrastructureExceptionListeningCommandHandler && $command) {
                $this->doctrineRegistry->resetManager();
                $this->innerService->onInfrastructureException($e, $command);
                $this->doctrineRegistry->getManager()->flush();
            }
            throw $e;
        }
        return $result;
    }
}
