<?php

namespace Emhar\CqrsInfrastructureBundle\Command;

use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Exception\RuntimeException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritDoc}
 */
class TestQueueCommand extends ContainerAwareCommand
{
    static protected $defaultName = 'emhar-cqrs:queue:test';

    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Test jms job queue.')
            ->setHelp('This command return an invalid status code queue is not running');
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \JMS\JobQueueBundle\Exception\RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'Command Begin',
            '============',
            '',
        ]);

        $job = new Job(
            'emhar-cqrs:queue:test-internal',
            array(),
            true,
            Job::DEFAULT_QUEUE,
            Job::PRIORITY_HIGH
        );
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
        $em->persist($job);
        $em->flush();
        $i = 0;
        while ($i++ < 30) {
            sleep(1);
            $em->refresh($job);
            if ($job->getState() == Job::STATE_FINISHED) {
                $output->writeln([
                    'Queue is running',
                    '============',
                    '',
                ]);
                return;
            }
        }
        throw new RuntimeException('Queue seems to be inactive...');
    }
}
