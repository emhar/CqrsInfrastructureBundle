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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * {@inheritDoc}
 */
class RepositoryDoctrineEventCollectorPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('emhar_cqrs.doctrine_event_collector')) {
            return;
        }
        $taggedServices = $container->findTaggedServiceIds('emhar_cqrs_repository');
        $references = array();
        foreach ($taggedServices as $id => $taggedService) {
            $references[$taggedService[0]['alias']] = new Reference($id);
        }
        $container->findDefinition('emhar_cqrs.doctrine_event_collector')->replaceArgument(0, $references);
    }
}