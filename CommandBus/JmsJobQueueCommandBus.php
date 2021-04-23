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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Emhar\CqrsInfrastructure\CommandBus\CommandBusInterface;
use Emhar\CqrsInfrastructureBundle\Util\Debug;
use JMS\JobQueueBundle\Entity\Job;

class JmsJobQueueCommandBus implements CommandBusInterface
{
    /**
     * @var Registry
     */
    protected $doctrineRegistry;

    /**
     * @var array
     */
    protected $postedCommands = array();

    /**
     * @var array
     */
    protected $toInsertCommands = array();

    /**
     * @param Registry $doctrineRegistry
     */
    public function __construct(Registry $doctrineRegistry)
    {
        $this->doctrineRegistry = $doctrineRegistry;
    }

    /**
     * {@inheritDoc}
     * @throws \LogicException
     */
    public function getCommandResponse(CommandInterface $command, bool $enableUserNotification = true, array $options = array())
    {
        throw new \LogicException('Cannot use an asynchronous command and getting a response.');
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function postCommand(CommandInterface $command, bool $userNotificationEnabled = true, string $queue = self::DEFAULT_QUEUE, string $priority = self::PRIORITY_NORMAL, \DateTime $executeAfter = null, bool $isAsync = false, array $options = array(), int $retryCounter = 0)
    {
        if (!in_array($command, $this->postedCommands, false)) {
            $this->postedCommands[] = $command;
            $data = array('command' => $command, 'user-notification-enabled' => $userNotificationEnabled, 'queue' => $queue, 'priority' => $priority, 'executeAfter' => $executeAfter, 'options' => $options, 'retryCounter' => $retryCounter);
            $this->toInsertCommands[] = $data;
        }
    }

    public function dispatchPostedCommand(CqrsEventsCollectedEvent $cqrsEventsCollectedEvent)
    {
        foreach ($this->toInsertCommands as $data) {
            $command = $data['command'];
            $serializedCommand = serialize($command);
            $encodedCommand = base64_encode($serializedCommand);
            $em = $this->doctrineRegistry->getManager();
            $args = array(
                'serialized-command' => $encodedCommand,
                'user-notification-enabled' => $data['user-notification-enabled'],
                'execution-id' => $cqrsEventsCollectedEvent->getExecutionId(),
                'options' => json_encode(array_merge($data['options'], $cqrsEventsCollectedEvent->getOptions())),
                'retryCounter' => $data['retryCounter']
            );
            $commandName = 'emhar-cqrs:core-command:run';
            /* @see \JMS\JobQueueBundle\Entity\Repository\JobRepository::findJob() */
            //Same current with a criteria on state

            $findArgs = $args;
            $findArgs['execution-id'] = '%';
            $findArgs['options'] = '%';
            $findArgs['retryCounter'] = '%';

            $pendingJob = $em
                ->createQuery(
                    'SELECT j FROM JMSJobQueueBundle:Job j'
                    . ' WHERE j.command = :command AND j.state = :state AND j.args LIKE :args'
                )
                ->setParameter('command', $commandName)
                ->setParameter('args', json_encode($findArgs))
                ->setParameter('state', Job::STATE_PENDING)
                ->setMaxResults(1)
                ->getOneOrNullResult();
            if (!$pendingJob) {
                $job = new Job($commandName, $args, true, $data['queue'], $data['priority']);
                $job->addOutput(Debug::dump($command, 10, true, false));
                if ($data['executeAfter']) {
                    $job->setExecuteAfter($data['executeAfter']);
                }
                $em->persist($job);
            }
        }
        $this->toInsertCommands = array();
    }

    public function cancelPostedCommand()
    {
        $this->toInsertCommands = array();
        $this->postCommands = array();
    }

    /**
     * @return array
     */
    public function getPostedCommands(): array
    {
        return $this->postedCommands;
    }
}