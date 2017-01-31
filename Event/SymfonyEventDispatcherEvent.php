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

use Emhar\CqrsInfrastructure\Event\Event;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * {@inheritDoc}
 */
class SymfonyEventDispatcherEvent extends SymfonyEvent
{
    /**
     * @var Event
     */
    protected $event;

    /**
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }
}