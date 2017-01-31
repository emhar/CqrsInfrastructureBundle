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

use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * {@inheritDoc}
 */
class SymfonyEventDispatcherCommandEvent extends Event
{
    const EVENT_NAME = 'COMMAND_EVENT';

    /**
     * @var CommandInterface
     */
    protected $command;

    /**
     * @var mixed|null
     */
    protected $response;

    /**
     * Command execution time
     *
     * @var float
     */
    protected $executionTime = 0;

    /**
     * Command start time
     *
     * @var float
     */
    protected $executionStart;

    /**
     * @param CommandInterface $command
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
    }

    /**
     * @return CommandInterface
     */
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    /**
     * @return mixed|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param mixed $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @return float
     */
    public function getExecutionStart()
    {
        return $this->executionStart;
    }

    /**
     * Set execution start of a request
     *
     * @return SymfonyEventDispatcherCommandEvent
     */
    public function setExecutionStart()
    {
        $this->executionStart = microtime();

        return $this;
    }

    /**
     * Stop the execution of a request
     * and set the request execution time
     *
     * @return SymfonyEventDispatcherCommandEvent
     */
    public function setExecutionStop()
    {
        $this->executionTime = microtime() - $this->executionStart;

        return $this;
    }

    /**
     * Return execution time in milliseconds
     *
     * @return float
     */
    public function getTiming()
    {
        return $this->getExecutionTime() * 1000;
    }

    /**
     * @return float
     */
    public function getExecutionTime()
    {
        return $this->executionTime;
    }
}