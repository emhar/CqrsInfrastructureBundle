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

use Symfony\Component\EventDispatcher\Event;

/**
 * {@inheritDoc}
 */
class CqrsEventsCollectedEvent extends Event
{
    /**
     * @var mixed
     */
    protected $executionId;

    /**
     * @param mixed $executionId
     */
    public function __construct($executionId)
    {
        $this->executionId = $executionId;
    }

    /**
     * @return mixed
     */
    public function getExecutionId()
    {
        return $this->executionId;
    }
}