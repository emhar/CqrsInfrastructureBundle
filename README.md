# CQRS Infrastructure Symfony 3 Bundle

This bundle is a Symfony 3, Doctrine ORM implementation of this library.

It provides two command bus:
* ```emhar_cqrs.synchronous_command_bus```
A synchronous bus based on Symfony event dispatcher.

* ```emhar_cqrs.asynchronous_command_bus```
An asynchronous bus using JMSJobQueueBundle and the synchronous bus.
This bus does not insert into its queue duplicate commands.

You can see collected information about this two bus in Symfony profiler.

This bundle also provides a command handler decorator that encapsulate process in a Doctrine transaction.

Command handler decorator collects events in model classes ***processed by Doctrine*** and in repositories.
Events are sent to your subscriber with a mechanism based on Symfony event dispatcher.

## Installation
### Step one: JmsJobQueueBundle

See bundle documentation.
You must have a service like ```supervior``` to run this command:
```bash
php %kernel.root_dir%/console jms-job-queue:run --env=prod --verbose
```


### Step two: Add a repository to composer

To add a new repository, add this lines in your composer.json
```json
{
    ...
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/emhar/CqrsInfrastructureBundle.git"
        }
    ]
    ...
}
```

### Step three: Download bundle
Open a command console, enter your project directory
and execute the following command to download the latest stable version of this bundle:
```bash
$ composer require emhar/cqrs-infrastructure-bundle
```

### Step four: Enable the Bundle

Then, enable the bundle by adding the following line in the app/AppKernel.php file of your project:

```php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Emhar\CqrsInfrastructureBundle\EmharCqrsInfrastructureBundle(),
        );

        // ...
    }

    // ...
}
```
## Usage

### Command

Registry command handlers as service:
```yml
services:
    acme_command.flight_create_handler:
        class: Acme\Core\CommandHandler\UserRegisterHandler
        arguments:
            - '@acme_repository.user_repository'
        tags:
            - { name: emhar_cqrs.command_handler, method: process, event: Acme\Core\Command\UserRegisterCommand }
```

### Repository

Registry repositories as service:
```yml
services:
    acme_repository.user_repository:
        class: Acme\CoreBundle\Repository\UserRepository
        arguments: ['@doctrine']
        tags:
             - { name: "emhar_cqrs_repository", alias: "user" }
```

### Event subscriber

Registry subscribers as service, for example with asynchronous bus as argument:
```yml
services:
    acme_event_subscriberuser_registration_mail_subscriber:
        class: Acme\Core\EventSubscriber\UserRegistrationMailSubscriber
        arguments: ['@emhar_cqrs.asynchronous_command_bus']
        tags:
            - { name: emhar_cqrs.event_subscriber }
```