<?php

namespace Emhar\CqrsInfrastructureBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PauseQueueCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'emhar-cqrs:queue:pause';

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Pause all jobs in queue');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ManagerRegistry $registry */
        $registry = $this->getContainer()->get('doctrine');

        /** @var EntityManager $em */
        $em = $registry->getManagerForClass('JMSJobQueueBundle:Job');
        $i = 0;
        do {
            $i++;
            sleep(1);
            try {
                $this->pauseAllPendingJobs($em);
                $count = $this->countAllRunningJob($em);
                $output->writeln('Still ' . $count . ' jobs running queue');
            } catch (\Exception $e) {
                $count = 1;
                $output->writeln('Fail to pause running jobs');
            }
            if ($i > 1000) {
                throw new \Exception('Cannot pause running jobs, 1000 loops are not enough');
            }
        } while ((int)$count !== 0);
        return 0;
    }

    /**
     * @param EntityManager $em
     */
    protected function pauseAllPendingJobs(EntityManager $em)
    {
        $repo = $em->getRepository('JMSJobQueueBundle:Job');
        $qb = $repo->createQueryBuilder('j');
        $qb->update()
            ->set('j.state', ':new_state')
            ->setParameter('new_state', Job::STATE_NEW)
            ->where($qb->expr()->eq('j.state', ':pending_state'))
            ->setParameter('pending_state', Job::STATE_PENDING);
        $qb->getQuery()->execute();
    }

    /**
     * @param EntityManager $em
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function countAllRunningJob(EntityManager $em)
    {
        $repo = $em->getRepository('JMSJobQueueBundle:Job');
        $qb = $repo->createQueryBuilder('j');
        $qb->select($qb->expr()->count('j.id'))
            ->where($qb->expr()->eq('j.state', ':running_state'))
            ->setParameter('running_state', Job::STATE_RUNNING);
        return $qb->getQuery()->getSingleScalarResult();
    }

}