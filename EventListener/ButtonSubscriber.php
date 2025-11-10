<?php

namespace MauticPlugin\MauticAiConsoleBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ButtonSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectAiConsoleButton', 0],
        ];
    }

    public function injectAiConsoleButton(CustomButtonEvent $event): void
    {
        // Only add to navbar
        if (ButtonHelper::LOCATION_NAVBAR !== $event->getLocation()) {
            return;
        }

        // Always show the button for now - TODO: Add integration check back later
        $event->addButton([
            'attr' => [
                'href' => 'javascript:void(0);',
                'class' => 'btn btn-ghost btn-icon btn-nospin ai-console-toggle',
                'id' => 'ai-console-toggle',
                'title' => 'AI Console',
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
            ],
            'iconClass' => 'ri-sparkling-line', // Using Remixicon sparkling as close to stars
            'priority' => 100,
        ]);
    }
}