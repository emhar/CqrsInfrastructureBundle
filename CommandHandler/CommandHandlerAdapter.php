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

class CommandHandlerAdapter
{
    /**
     * @var AbstractCommandHandler
     */
    protected $innerService;

    /**
     * @var ManagerRegistry
     */
    protected $doctrineRegistry;

    /**
     * @var CommandHandlerAdapterUtils
     */
    protected $utils;

    /**
     * @param ManagerRegistry $doctrineRegistry
     * @param CommandHandlerAdapterUtils $utils
     */
    public function __construct(ManagerRegistry $doctrineRegistry, CommandHandlerAdapterUtils $utils)
    {
        $this->doctrineRegistry = $doctrineRegistry;
        $this->utils = $utils;
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
        if ($name === 'setInnerService') {
            call_user_func_array(array($this, 'setInnerService'), $arguments);
        }
        $executionId = uniqid('', true);
        if ($commandEvent = $this->utils->getCommandEvent($arguments)) {
            $this->innerService->setUserNotificationEnabled($commandEvent->isUserNotificationEnabled());
            $this->innerService->setOptions($commandEvent->getOptions());
            if ($commandEvent->getExecutionId()) {
                $executionId = $commandEvent->getExecutionId();
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
            $this->utils->dispatchModelEvents($commandEvent);
            $this->doctrineRegistry->getManager()->flush();
            $this->utils->logCommand($commandEvent, $executionId, 'info');
            $em->commit();
            $this->utils->runModelEvents($executionId);
            $this->doctrineRegistry->getManager()->flush();
        } catch (\Exception $e) {
            $this->utils->handleError($this->innerService, $e, $commandEvent, $executionId);
        }
        return $result ?? null;
    }
}
