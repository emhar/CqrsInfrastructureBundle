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
use Emhar\CqrsInfrastructure\CommandHandler\AbstractCommandHandler;
use Emhar\CqrsInfrastructure\CommandHandler\AbstractInfrastructureExceptionListeningCommandHandler;
use Emhar\CqrsInfrastructure\Event\EventContainerInterface;
use Emhar\CqrsInfrastructureBundle\CommandBus\SymfonyEventDispatcherCommandEvent;
use Emhar\CqrsInfrastructureBundle\CommandBus\CqrsEventsCollectedEvent;
use Emhar\CqrsInfrastructureBundle\Event\SymfonyEventDispatcherEvent;
use Emhar\CqrsInfrastructureBundle\Util\Debug;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
     * @param ManagerRegistry $doctrineRegistry
     * @param EventContainerInterface $eventCollector
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param RequestStack $requestStack
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(ManagerRegistry $doctrineRegistry, EventContainerInterface $eventCollector, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger, RequestStack $requestStack, TokenStorageInterface $tokenStorage)
    {
        $this->doctrineRegistry = $doctrineRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventCollector = $eventCollector;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
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
        $executionId = uniqid('', true);
        $commandEvent = null;
        $command = null;
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof SymfonyEventDispatcherCommandEvent) {
                $commandEvent = $argument;
                $command = $argument->getCommand();
                $arguments[$key] = $command;
                $this->innerService->setUserNotificationEnabled($commandEvent->isUserNotificationEnabled());
                if ($argument->getExecutionId()) {
                    $executionId = $argument->getExecutionId();
                }
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
            $this->logCommand($command, $executionId);
            $cqrsEventsCollectedEvent = new CqrsEventsCollectedEvent($executionId);
            $this->eventDispatcher->dispatch('cqrs-events-collected', $cqrsEventsCollectedEvent);
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

    /**
     * @param CommandInterface $command
     * @param mixed $executionId
     */
    protected function logCommand(CommandInterface $command, $executionId)
    {
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
        $message .= Debug::dump($command, 10, true, false);
        $this->logger->info($message);
    }
}
