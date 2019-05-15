<?php

namespace Emhar\CqrsInfrastructureBundle\Command;

use JMS\JobQueueBundle\Command\ScheduleCommand;
use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Console\ScheduleHourly;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritDoc}
 */
class CleanUpQueueCommand extends ScheduleCommand implements CronCommand
{
    use ScheduleHourly;

    protected static $defaultName = 'emhar-cqrs:queue:clean-up';

    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Clean jms job table.')
            ->setHelp('This command must be executed once a day ...');
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
            'Command Begin',
            '============',
            '',
        ]);
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
        /* @var $em \Doctrine\ORM\EntityManager */
        $qb = $em->createQueryBuilder();
        $qb->delete('JMSJobQueueBundle:Job', 'j');
        $qb->where($qb->expr()->eq('j.state', ':state'));
        $qb->setParameter('state', Job::STATE_FINISHED);
        $qb->getQuery()->execute();
        $output->writeln([
            'Command Finish'
        ]);
    }
}
