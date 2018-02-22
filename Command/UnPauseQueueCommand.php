<?php

namespace Emhar\CqrsInfrastructureBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UnPauseQueueCommand extends ContainerAwareCommand
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName('emhar-cqrs:queue:un-pause')
            ->setDescription('Pause all jobs in queue');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ManagerRegistry $registry */
        $registry = $this->getContainer()->get('doctrine');

        /** @var EntityManager $em */
        $em = $registry->getManagerForClass('JMSJobQueueBundle:Job');
        $this->unPauseAllPendingJobs($em);
        return 0;
    }

    /**
     * @param EntityManager $em
     */
    protected function unPauseAllPendingJobs(EntityManager $em)
    {
        $repo = $em->getRepository('JMSJobQueueBundle:Job');
        $qb = $repo->createQueryBuilder('j');
        $qb->update()
            ->set('j.state', ':pending_state')
            ->setParameter('pending_state', Job::STATE_PENDING)
            ->where($qb->expr()->eq('j.state', ':new_state'))
            ->setParameter('new_state', Job::STATE_NEW);
        $qb->getQuery()->execute();
    }
}