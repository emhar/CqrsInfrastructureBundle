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
use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Emhar\CqrsInfrastructure\CommandBus\CommandBusInterface;
use Emhar\CqrsInfrastructure\CommandHandler\AbstractCommandHandler;
use Emhar\CqrsInfrastructure\CommandHandler\AbstractInfrastructureExceptionListeningCommandHandler;
use Emhar\CqrsInfrastructure\Event\EventContainerInterface;
use Emhar\CqrsInfrastructureBundle\CommandBus\CqrsEventsCollectedEvent;
use Emhar\CqrsInfrastructureBundle\CommandBus\JmsJobQueueCommandBus;
use Emhar\CqrsInfrastructureBundle\CommandBus\SymfonyEventDispatcherCommandEvent;
use Emhar\CqrsInfrastructureBundle\Event\SymfonyEventDispatcherEvent;
use Emhar\CqrsInfrastructureBundle\Util\Debug;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CommandHandlerAdapterUtils
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ManagerRegistry
     */
    protected $doctrineRegistry;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var JmsJobQueueCommandBus
     */
    protected $asyncBus;

    /**
     * @var EventContainerInterface
     */
    protected $eventCollector;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param ManagerRegistry $doctrineRegistry
     * @param LoggerInterface $logger
     * @param RequestStack $requestStack
     * @param TokenStorageInterface $tokenStorage
     * @param JmsJobQueueCommandBus $asyncBus
     * @param EventContainerInterface $eventCollector
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, ManagerRegistry $doctrineRegistry, LoggerInterface $logger, RequestStack $requestStack, TokenStorageInterface $tokenStorage, JmsJobQueueCommandBus $asyncBus, EventContainerInterface $eventCollector)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->doctrineRegistry = $doctrineRegistry;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->asyncBus = $asyncBus;
        $this->eventCollector = $eventCollector;
    }


    /**
     * @param SymfonyEventDispatcherCommandEvent|null $commandEvent
     * @param $executionId
     */
    public function logCommand(SymfonyEventDispatcherCommandEvent $commandEvent = null, $executionId)
    {
        if ($commandEvent) {
            $message = '';
            if ($executionId) {
                $message .= 'EId:' . $executionId . ',';
            }
            if ($token = $this->tokenStorage->getToken()) {
                $message .= 'P:' . $token->getUsername() . ',';
            }
            if ($request = $this->requestStack->getMasterRequest()) {
                if ($request->headers->has('x-user-name')) {
                    $message .= 'U:' . $request->headers->get('x-user-name') . ',';
                }
                $message .= 'Ips: ' . implode(';', $request->getClientIps()) . ', ';
            }
            $message .= Debug::dump($commandEvent->getCommand(), 10, true, false);
            $this->logger->info($message);
        }
    }

    /**
     * @param array $arguments
     * @return SymfonyEventDispatcherCommandEvent|null
     */
    public function getCommandEvent(array &$arguments)
    {
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof SymfonyEventDispatcherCommandEvent) {
                $arguments[$key] = $argument->getCommand();
                return $argument;
            }
        }
        return null;
    }

    /**
     * @param SymfonyEventDispatcherCommandEvent|null $commandEvent
     */
    public function dispatchModelEvents(SymfonyEventDispatcherCommandEvent $commandEvent = null)
    {
        $events = $this->eventCollector->getEvents();
        foreach ($events as $event) {
            $event->setUserNotificationEnabled($commandEvent ? $commandEvent->isUserNotificationEnabled() : true);
            $this->eventDispatcher->dispatch(get_class($event), new SymfonyEventDispatcherEvent($event));
        }
    }

    /**
     * @param string $executionId
     */
    public function runModelEvents(string $executionId)
    {
        $cqrsEventsCollectedEvent = new CqrsEventsCollectedEvent($executionId);
        $this->eventDispatcher->dispatch('cqrs-events-collected', $cqrsEventsCollectedEvent);
    }

    /**
     * @param EntityManager $em
     * @param \Exception $e
     * @param CommandInterface $command
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function handleError(AbstractCommandHandler $handler, \Exception $e, SymfonyEventDispatcherCommandEvent $commandEvent = null, string $executionId)
    {
        $em = $this->doctrineRegistry->getManager();
        while ($em->getConnection()->getTransactionNestingLevel() > 0) {
            $em->getConnection()->rollback();
        }
        $em->close();
        if ($commandEvent) {
            if ($commandEvent->isAsync()
                && $handler->canBeRetried($e, $commandEvent->getRetryCounter())
            ) {
                $this->doctrineRegistry->resetManager();
                $this->asyncBus->cancelPostedCommand();
                $this->asyncBus->postCommand(
                    $commandEvent->getCommand(),
                    $commandEvent->isUserNotificationEnabled(),
                    CommandBusInterface::DEFAULT_QUEUE,
                    CommandBusInterface::PRIORITY_NORMAL,
                    null,
                    false,
                    $commandEvent->getOptions(),
                    $commandEvent->getRetryCounter() + 1
                );
                $this->runModelEvents($executionId);
                $this->doctrineRegistry->getManager()->flush();
                $commandEvent->setError($e);
                return;
            }
            if ($handler instanceof AbstractInfrastructureExceptionListeningCommandHandler) {
                $this->doctrineRegistry->resetManager();
                $handler->onInfrastructureException($e, $commandEvent->getCommand());
                $this->doctrineRegistry->getManager()->flush();
            }
        }
        throw $e;
    }
}