<?php

namespace MauticPlugin\MauticAiConsoleBundle\Tools;

use Mautic\CoreBundle\Helper\CacheHelper;

class ClearCacheTool
{
    private CacheHelper $cacheHelper;

    public function __construct(CacheHelper $cacheHelper = null)
    {
        // Allow for manual injection for now
        if ($cacheHelper) {
            $this->cacheHelper = $cacheHelper;
        }
    }

    public function getTitle(): string
    {
        return 'Clear Cache';
    }

    public function getDescription(): string
    {
        return 'Tool to clear Mautic cache';
    }

    public function getParameters(): array
    {
        return [];
    }

    public function execute(array $parameters = []): array
    {
        try {
            // Check if cacheHelper is available
            if (!$this->cacheHelper) {
                return [
                    'success' => false,
                    'error' => 'Cache helper not available',
                ];
            }

            // Simply nuke the entire cache
            $this->cacheHelper->nukeCache();

            return [
                'success' => true,
                'message' => 'Cache cleared successfully. The application will regenerate cache files as needed.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error clearing cache: ' . $e->getMessage(),
            ];
        }
    }
}