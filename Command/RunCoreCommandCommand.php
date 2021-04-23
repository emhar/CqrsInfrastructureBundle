<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\Command;

use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Emhar\CqrsInfrastructure\CommandBus\CommandBusInterface;
use Emhar\CqrsInfrastructureBundle\CommandBus\CqrsEventsCollectedEvent;
use Emhar\CqrsInfrastructureBundle\Util\Debug;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritDoc}
 */
class RunCoreCommandCommand extends ContainerAwareCommand
{

    protected static $defaultName = 'emhar-cqrs:core-command:run';

    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Run a cqrs command.')
            ->setHelp('Deserialize given cqrs command and run')
            ->addArgument('serialized-command', InputArgument::REQUIRED, 'The serialized command.')
            ->addArgument('user-notification-enabled', InputArgument::OPTIONAL, 'If set to false, disable email notification.')
            ->addArgument('execution-id', InputArgument::OPTIONAL, 'To trace first command which has generated this jobs.')
            ->addArgument('options', InputArgument::OPTIONAL, 'Option are availlable in command handler and given on generated sub commmands.', '[]')
            ->addArgument('retryCounter', InputArgument::OPTIONAL, 'Counter of retried jobs in case of fail.', 0);
    }

    /**
     * {@inheritDoc}
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'Command Begin',
            '============',
            '',
        ]);
        $serializedCommand = $input->getArgument('serialized-command');
        $userNotificationEnabled = true;
        if ($input->hasArgument('user-notification-enabled')
            && $input->getArgument('user-notification-enabled') !== null
        ) {
            $userNotificationEnabled = $input->getArgument('user-notification-enabled');
        }
        $command = unserialize(base64_decode($serializedCommand), array('allowed_classes' => true));
        if ($command instanceof CommandInterface) {
            $output->writeln(Debug::dump($command, 10, true, false));
            $bus = $this->getContainer()->get('emhar_cqrs.synchronous_command_bus');
            $bus->postCommand(
                $command,
                $userNotificationEnabled,
                CommandBusInterface::DEFAULT_QUEUE,
                CommandBusInterface::PRIORITY_NORMAL,
                null,
                true,
                json_decode($input->getArgument('options') ?? array(), true),
                $input->getArgument('retryCounter') ?? 0
            );
            $event = new CqrsEventsCollectedEvent($input->getArgument('execution-id'));
            $bus->dispatchPostedCommand($event);
            foreach ($event->getErrors() as $error) {
                if ($output instanceof ConsoleOutput) {
                    $output->getErrorOutput()->writeln(get_class($error) . ': ' . $error->getMessage() . ' ' . $error->getTraceAsString());
                } else {
                    $output->writeln('<error>' . get_class($error) . ': ' . $error->getMessage() . ' ' . $error->getTraceAsString() . '</error>');
                }
            }
            $output->writeln([
                'Tasks Finish',
                '============',
                '',
            ]);
        } else {
            throw new \InvalidArgumentException('RunCoreCommand doesn\'t support ' . get_class($command));
        }
    }
}