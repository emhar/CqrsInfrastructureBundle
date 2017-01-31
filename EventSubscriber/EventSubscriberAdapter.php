<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\EventSubscriber;

use Emhar\CqrsInfrastructure\EventSubscriber\EventSubscriberInterface;
use Emhar\CqrsInfrastructureBundle\Event\SymfonyEventDispatcherEvent;

/**
 * {@inheritDoc}
 */
class EventSubscriberAdapter
{
    /**
     * @var EventSubscriberInterface
     */
    protected $innerService;

    /**
     * @param EventSubscriberInterface $innerService
     */
    public function setInnerService(EventSubscriberInterface $innerService)
    {
        $this->innerService = $innerService;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof SymfonyEventDispatcherEvent) {
                $arguments[$key] = $argument->getEvent();
            }
        }
        return call_user_func_array(array($this->innerService, $name), $arguments);
    }
}