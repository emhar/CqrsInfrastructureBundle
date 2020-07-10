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
class CommandHandlerAdapterPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds('emhar_cqrs.command_handler');
        $decorators = array();
        $adapterDefinition = new Definition(CommandHandlerAdapter::class, array(
            new Reference('doctrine'),
            new Reference('emhar_cqrs.doctrine_event_collector'),
            new Reference('event_dispatcher'),
            new Reference('monolog.logger.emhar_command'),
            new Reference('request_stack'),
            new Reference('security.token_storage')
        ));
        foreach ($taggedServices as $id => $taggedService) {
            $decoratorId = $id . '_decorator';
            $definition = clone $adapterDefinition;
            $definition->addMethodCall('setInnerService', array(new Reference($decoratorId . '.inner')));
            $definition->setDecoratedService($id);
            $definition->addTag('monolog.logger', array('channel'=> 'emhar_command'));
            $decorators[$decoratorId] = $definition;
        }
        $container->addDefinitions($decorators);
    }
}