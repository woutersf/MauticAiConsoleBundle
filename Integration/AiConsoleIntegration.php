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

    public function getDescription(): string
    {
        return 'AI-powered console interface for Mautic. Configuration for this plugin is available in Settings → Configuration (or /s/config/edit).';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function getRequiredKeyFields(): array
    {
        return [];
    }

    /**
     * Get the path to the integration icon
     */
    public function getIcon(): string
    {
        return 'plugins/MauticAiConsoleBundle/Assets/mauticbot.png';
    }
}