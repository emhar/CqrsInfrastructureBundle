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

use Emhar\CqrsInfrastructureBundle\EventSubscriber\EventSubscriberAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * {@inheritDoc}
 */
class EventSubscriberAdapterPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds('emhar_cqrs.event_subscriber');
        $decorators = array();
        $adapterDefinition = new Definition(EventSubscriberAdapter::class, array());
        foreach ($taggedServices as $id => $taggedService) {
            $decoratorId = $id . '_decorator';
            $definition = clone $adapterDefinition;
            $definition->addMethodCall('setInnerService', array(new Reference($decoratorId . '.inner')));
            $definition->setDecoratedService($id);
            $decorators[$decoratorId] = $definition;
        }
        $container->addDefinitions($decorators);
    }
}