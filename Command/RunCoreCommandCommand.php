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

use Doctrine\Common\Util\Debug;
use Emhar\CqrsInfrastructure\Command\CommandInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritDoc}
 */
class RunCoreCommandCommand extends ContainerAwareCommand
{

    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName('emhar_cqrs:core-command:run')
            ->setDescription('Run a cqrs command.')
            ->setHelp('Deserialize given cqrs command and run')
            ->addArgument('serialized-command', InputArgument::REQUIRED, 'The serialized command.')
            ->addArgument('user-notification-enabled', InputArgument::OPTIONAL, 'If set to false, disable email notification.');
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
        if($input->hasArgument('user-notification-enabled')
            && $input->getArgument('user-notification-enabled') !== null
        ){
            $userNotificationEnabled= $input->getArgument('user-notification-enabled');
        }
        $command = unserialize(base64_decode($serializedCommand), array('allowed_classes' => true));
        if ($command instanceof CommandInterface) {
            $output->writeln(Debug::dump($command,10, true, false));
            $bus = $this->getContainer()->get('emhar_cqrs.synchronous_command_bus');
            $bus->getCommandResponse($command, $userNotificationEnabled);
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