<?php

namespace MauticPlugin\MauticAiConsoleBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\MauticAiConsoleBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'MauticAiConsoleBundle',
            'formType'   => ConfigType::class,
            'formAlias'  => 'aiconsoleconfig',
            'formTheme'  => '@MauticAiConsole/FormTheme/Config/_config_aiconsoleconfig_widget.html.twig',
            'parameters' => $event->getParametersFromConfig('MauticAiConsoleBundle'),
        ]);
    }
}