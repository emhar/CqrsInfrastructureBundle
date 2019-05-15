<?php

namespace Emhar\CqrsInfrastructureBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritDoc}
 */
class TestQueueInternalCommand extends ContainerAwareCommand
{

    protected static $defaultName = 'emhar-cqrs:queue:test-internal';

    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Command launched by emhar-cqrs:queue:test.')
            ->setHelp('Do nothing');
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'Queue test',
            '============',
            '',
        ]);
    }
}
