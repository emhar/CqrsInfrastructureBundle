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
use Doctrine\Common\Util\Debug;
use Doctrine\DBAL\Types\Type;
use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Emhar\CqrsInfrastructure\CommandBus\CommandBusInterface;
use JMS\JobQueueBundle\Entity\Job;

class JmsJobQueueCommandBus implements CommandBusInterface
{
    /**
     * @var Registry
     */
    protected $doctrineRegistry;

    protected $postedCommands = array();
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
    public function getCommandResponse(CommandInterface $command, bool $enableUserNotification = true)
    {
        throw new \LogicException('Cannot use an asynchronous command and getting a response.');
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function postCommand(CommandInterface $command, bool $userNotificationEnabled = true, string $queue = self::DEFAULT_QUEUE)
    {
        if (!in_array($command, $this->postedCommands, false)) {
            $this->postedCommands[] = $command;
            $data = array('command' => $command, 'user-notification-enabled' => $userNotificationEnabled, 'queue' => $queue);
            $this->toInsertCommands[] = $data;
        }
    }

    public function dispatchPostedCommand()
    {
        foreach ($this->toInsertCommands as $data) {
            $command = $data['command'];
            $serializedCommand = serialize($command);
            $encodedCommand = base64_encode($serializedCommand);
            $em = $this->doctrineRegistry->getManager();
            $args = array(
                'serialized-command' => $encodedCommand,
                'user-notification-enabled' => $data['user-notification-enabled'],
            );
            $commandName = 'emhar_cqrs:core-command:run';
            /* @see \JMS\JobQueueBundle\Entity\Repository\JobRepository::findJob() */
            //Same current with a criteria on state
            $pendingJob = $em
                ->createQuery(
                    'SELECT j FROM JMSJobQueueBundle:Job j'
                    . ' WHERE j.command = :command AND j.args = :args AND j.state = :state'
                )
                ->setParameter('command', $commandName)
                ->setParameter('args', $args, Type::JSON_ARRAY)
                ->setParameter('state', Job::STATE_PENDING)
                ->setMaxResults(1)
                ->getOneOrNullResult();
            if (!$pendingJob) {
                $job = new Job($commandName, $args, true, $data['queue']);
                $job->addOutput(Debug::dump($command, 10, true, false));
                $em->persist($job);
            }
        }
    }

    /**
     * @return array
     */
    public function getPostedCommands(): array
    {
        return $this->postedCommands;
    }
}