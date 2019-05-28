<?php

/*
 * This file is part of the EmharCqrsInfrastructure bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\CqrsInfrastructureBundle\DependencyInjection\Compiler;

use Emhar\CqrsInfrastructureBundle\CommandHandler\CommandHandlerAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * {@inheritDoc}
 */
class JmsJobScheduledCommandPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function process(ContainerBuilder $container)
    {
        $cleanUpCommandDefinition = $container->findDefinition('emhar_cqrs.commands.cleanup_queue');
        if($container->hasDefinition('jms_job_queue.command.schedule')){
            $scheduleCommandDefinition = $container->findDefinition('jms_job_queue.command.schedule');
            $cleanUpCommandDefinition->setArguments($scheduleCommandDefinition->getArguments());
            $cleanUpCommandDefinition->setTags($scheduleCommandDefinition->getTags());
        }
    }
}