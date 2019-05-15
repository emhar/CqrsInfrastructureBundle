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

use Emhar\CqrsInfrastructure\EventSubscriber\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\ExtractingEventDispatcher;

/**
 * {@inheritDoc}
 */
class RegisterEventSubscriberPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    protected $dispatcherService;

    /**
     * @var string
     */
    protected $subscriberTag;

    /**
     * Constructor.
     *
     * @param string $dispatcherService Service name of the event dispatcher in processed container
     * @param string $subscriberTag Tag name used for subscribers
     */
    public function __construct($dispatcherService = 'event_dispatcher', $subscriberTag = 'emhar_cqrs.event_subscriber')
    {
        $this->dispatcherService = $dispatcherService;
        $this->subscriberTag = $subscriberTag;
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->dispatcherService) && !$container->hasAlias($this->dispatcherService)) {
            return;
        }

        $dispatcherDefinition = $container->findDefinition($this->dispatcherService);
        if(!method_exists($dispatcherDefinition->getClass(), 'addSubscriberService')){
            $extractingDispatcher = new ExtractingEventDispatcher();
        }
        foreach ($container->findTaggedServiceIds($this->subscriberTag) as $id => $attributes) {
            $subscriberDefinition = $container->getDefinition($id);
            if (!$subscriberDefinition->isPublic()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as event subscribers are lazy-loaded.', $id));
            }

            if ($subscriberDefinition->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" must not be abstract as event subscribers are lazy-loaded.', $id));
            }

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $container->getParameterBag()->resolveValue($subscriberDefinition->getClass());
            $interface = EventSubscriberInterface::class;

            if (!is_subclass_of($class, $interface)) {
                if (!class_exists($class, false)) {
                    throw new \InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }

                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }
            if(isset($extractingDispatcher)){
                //SF 4
                $container->addObjectResource($class);

                ExtractingEventDispatcher::$subscriber = $class;
                $extractingDispatcher->addSubscriber($extractingDispatcher);
                foreach ($extractingDispatcher->listeners as $args) {
                    $args[1] = array(new ServiceClosureArgument(new Reference($id)), $args[1]);
                    $dispatcherDefinition->addMethodCall('addListener', $args);

                    if (isset($this->hotPathEvents[$args[0]])) {
                        $container->getDefinition($id)->addTag('container.hot_path');
                    }
                }
                $extractingDispatcher->listeners = array();
            } else {
                //SF 3
                $dispatcherDefinition->addMethodCall('addSubscriberService', array($id, $class));
            }
        }
    }
}