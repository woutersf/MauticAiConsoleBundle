<?php

namespace MauticPlugin\MauticAiConsoleBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

class AiConsoleIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'AiConsole';
    }

    public function getDisplayName(): string
    {
        return 'AI Console';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function getRequiredKeyFields(): array
    {
        return [];
    }
}