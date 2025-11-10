<?php

namespace MauticPlugin\MauticAiConsoleBundle\Tools;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;

class CreateSegmentTool
{
    private ListModel $listModel;

    public function __construct(ListModel $listModel = null)
    {
        // Allow for manual injection for now
        if ($listModel) {
            $this->listModel = $listModel;
        }
    }

    public function getTitle(): string
    {
        return 'Create Segment';
    }

    public function getDescription(): string
    {
        return 'Tool to create a contact segment in Mautic';
    }

    public function getParameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'Name of the segment',
                'required' => true,
            ],
            'active' => [
                'type' => 'boolean',
                'description' => 'Whether the segment should be active (defaults to true)',
                'required' => false,
            ],
        ];
    }

    public function execute(array $parameters = []): array
    {
        try {
            // Validate required parameters
            if (empty($parameters['name'])) {
                return [
                    'success' => false,
                    'error' => 'Segment name is required',
                ];
            }

            $segmentName = $parameters['name'];
            $isActive = isset($parameters['active']) ? (bool)$parameters['active'] : true;

            // Create new segment entity
            $segment = new LeadList();
            $segment->setName($segmentName);
            $segment->setPublicName($segmentName);
            $segment->setAlias(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $segmentName)) . '_' . time());
            $segment->setIsPublished($isActive);
            $segment->setDateAdded(new \DateTime());
            $segment->setDateModified(new \DateTime());

            // Get the list model to save the segment
            if ($this->listModel) {
                // Save the segment using the model
                $this->listModel->saveEntity($segment);

                $segmentUrl = '/s/segments/edit/' . $segment->getId();

                return [
                    'success' => true,
                    'message' => 'Segment <strong>"' . $segmentName . '"</strong> created successfully' . ($isActive ? ' <span style="color: #28a745;">(active)</span>' : ' <span style="color: #dc3545;">(inactive)</span>') . '. <a href="' . $segmentUrl . '" target="_blank">View segment</a>',
                    'segment_id' => $segment->getId(),
                    'segment_name' => $segmentName,
                    'is_active' => $isActive,
                    'alias' => $segment->getAlias(),
                    'url' => $segmentUrl,
                ];
            } else {
                // Fallback if listModel is not injected
                return [
                    'success' => false,
                    'error' => 'List model not available for segment creation',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}