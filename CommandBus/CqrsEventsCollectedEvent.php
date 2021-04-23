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
     * @var array
     */
    protected $options;

    /**
     * @var \Exception[]
     */
    protected $errors;

    /**
     * @param mixed $executionId
     */
    public function __construct($executionId, array $options = array())
    {
        $this->executionId = $executionId;
        $this->options = $options;
        $this->errors = array();
    }

    /**
     * @return mixed
     */
    public function getExecutionId()
    {
        return $this->executionId;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return \Exception[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param \Exception $error
     */
    public function addError(\Exception $error)
    {
        $this->errors[] = $error;
    }
}