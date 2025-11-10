<?php

namespace MauticPlugin\MauticAiConsoleBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    public function injectAssets(CustomAssetsEvent $event): void
    {
        // Add CSS to head
        $event->addStylesheet('plugins/MauticAiConsoleBundle/Assets/css/console.css');

        // Add JavaScript to body (before closing body tag)
        $event->addScript('plugins/MauticAiConsoleBundle/Assets/js/console.js', 'bodyClose');
    }
}