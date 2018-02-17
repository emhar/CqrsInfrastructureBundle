<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Emhar\CqrsInfrastructure\Event\EventContainerInterface;

abstract class AbstractDoctrineRepository implements EventContainerInterface
{
    /**
     * @var Registry
     */
    protected $doctrineRegistry;

    /**
     * @var array
     */
    protected $events = array();

    /**
     * @param Registry $doctrineRegistry
     */
    public function __construct(Registry $doctrineRegistry)
    {
        $this->doctrineRegistry = $doctrineRegistry;
    }

    /**
     * {@inheritDoc}
     */
    public function getEvents()
    {
        return $this->events;
    }

    public function eraseEvents()
    {
        $this->events = array();
    }

    /**
     * @return EntityManager
     */
    protected function getDoctrineManager(): EntityManager
    {
        try{
            $manager = $this->doctrineRegistry->getManager();
            /* @var $manager EntityManager */
        } catch (\InvalidArgumentException $e){
            trigger_error('No entity manager defined', E_USER_ERROR);
            $manager = null;
        }
        return $manager;
    }
}
