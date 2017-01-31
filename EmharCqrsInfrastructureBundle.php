<?php

namespace Emhar\CqrsInfrastructureBundle;

use Emhar\CqrsInfrastructureBundle\DependencyInjection\Compiler\CommandHandlerAdapterPass;
use Emhar\CqrsInfrastructureBundle\DependencyInjection\Compiler\EventSubscriberAdapterPass;
use Emhar\CqrsInfrastructureBundle\DependencyInjection\Compiler\RegisterEventSubscriberPass;
use Emhar\CqrsInfrastructureBundle\DependencyInjection\Compiler\RepositoryDoctrineEventCollectorPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritDoc}
 */
class EmharCqrsInfrastructureBundle extends Bundle
{

    /**
     * {@inheritDoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new RepositoryDoctrineEventCollectorPass());
        $container->addCompilerPass(new CommandHandlerAdapterPass());
        $container->addCompilerPass(new EventSubscriberAdapterPass());
        $container->addCompilerPass(new RegisterEventSubscriberPass(
            'event_dispatcher',
            'emhar_cqrs.event_subscriber'
        ), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new RegisterListenersPass(
            'event_dispatcher',
            'emhar_cqrs.command_handler',
            'emhar_cqrs.command_subscriber'
        ), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
