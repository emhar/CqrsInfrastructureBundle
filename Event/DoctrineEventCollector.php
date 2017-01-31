<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\Event;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Emhar\CqrsInfrastructure\Event\AbstractEventContainer;
use Emhar\CqrsInfrastructure\Event\EventContainerInterface;
use Emhar\CqrsInfrastructureBundle\Repository\AbstractDoctrineRepository;
use Symfony\Component\EventDispatcher\Event;

/**
 * {@inheritDoc}
 */
class DoctrineEventCollector implements EventSubscriber, EventContainerInterface
{
    /**
     * @var Event[]
     */
    protected $collectedEvents = array();

    /**
     * @var AbstractDoctrineRepository[]
     */
    protected $repositories;

    /**
     * @param \Emhar\CqrsInfrastructureBundle\Repository\AbstractDoctrineRepository[] $repositories
     */
    public function __construct(array $repositories)
    {
        $this->repositories = $repositories;
    }


    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        );
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event)
    {
        $this->collectEventsFromEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    private function collectEventsFromEntity(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ($entity instanceof AbstractEventContainer) {
            foreach ($entity->getEvents() as $modelEvent) {
                $this->collectedEvents[] = $modelEvent;
            }
            $entity->eraseEvents();
        }
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->collectEventsFromEntity($event);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postRemove(LifecycleEventArgs $event)
    {
        $this->collectEventsFromEntity($event);
    }

    /**
     * {@inheritDoc}
     */
    public function getEvents()
    {
        $collectedEventsCollections = array($this->collectedEvents);
        foreach ($this->repositories as $repository) {
            $collectedEventsCollections[] = $repository->getEvents();
            $repository->eraseEvents();
        }
        return array_merge(...$collectedEventsCollections);
    }

    public function eraseEvents()
    {
        $this->collectedEvents = array();
    }
}