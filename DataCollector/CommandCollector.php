<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\DataCollector;

use Doctrine\Common\Util\Debug;
use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Emhar\CqrsInfrastructureBundle\CommandBus\JmsJobQueueCommandBus;
use Emhar\CqrsInfrastructureBundle\CommandBus\SymfonyEventDispatcherCommandBus;
use Emhar\CqrsInfrastructureBundle\CommandBus\SymfonyEventDispatcherCommandEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * {@inheritDoc}
 */
class CommandCollector extends DataCollector
{

    /**
     * @var JmsJobQueueCommandBus
     */
    protected $asynchronousCommandBus;

    /**
     * @var SymfonyEventDispatcherCommandBus
     */
    protected $synchronousCommandBus;

    /**
     * @param SymfonyEventDispatcherCommandBus $synchronousCommandBus
     * @param JmsJobQueueCommandBus $asynchronousCommandBus
     */
    public function __construct(SymfonyEventDispatcherCommandBus $synchronousCommandBus, JmsJobQueueCommandBus $asynchronousCommandBus)
    {
        $this->asynchronousCommandBus = $asynchronousCommandBus;
        $this->synchronousCommandBus = $synchronousCommandBus;
        $this->data['emhar_cqrs_command'] = new \SplQueue();
        $this->data['emhar_cqrs_job'] = new \SplQueue();
    }


    /**
     * {@inheritDoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        foreach ($this->synchronousCommandBus->getExecutedEvents() as $event) {
            /* @var $event SymfonyEventDispatcherCommandEvent */
            $this->data['emhar_cqrs_command'][] = array(
                'command' => (new \ReflectionClass($event->getCommand()))->getShortName(),
                'arguments' => Debug::dump($event->getCommand(),10, true, false),
                'executionTime' => $event->getTiming()
            );
        }

        foreach ($this->asynchronousCommandBus->getPostedCommands() as $command) {
            /* @var $command CommandInterface */
            $this->data['emhar_cqrs_job'][] = array(
                'command' => (new \ReflectionClass($command))->getShortName(),
                'arguments' => Debug::dump($command,10, true, false)
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'emhar_cqrs_command';
    }

    /**
     * Return jms job command list
     *
     * @return \SplQueue
     */
    public function getJobs()
    {
        return $this->data['emhar_cqrs_job'];
    }

    /**
     * Return average time spent by command
     *
     * @return float
     */
    public function getAvgExecutionTime()
    {
        $totalExecutionTime = $this->getTotalExecutionTime();

        return $totalExecutionTime ? ($totalExecutionTime / count($this->getCommands())) : 0;
    }

    /**
     * Return the total time spent by command
     *
     * @return float
     */
    public function getTotalExecutionTime()
    {
        return array_reduce(iterator_to_array($this->getCommands()), function ($time, $value) {
            $time += $value['executionTime'];
            return $time;
        });
    }

    /**
     * Return command list
     *
     * @return \SplQueue
     */
    public function getCommands()
    {
        return $this->data['emhar_cqrs_command'];
    }
}