<?php

namespace MauticPlugin\MauticAiConsoleBundle\Tools;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;

class CreateContactTool
{
    private LeadModel $leadModel;

    public function __construct(LeadModel $leadModel = null)
    {
        // Allow for manual injection for now
        if ($leadModel) {
            $this->leadModel = $leadModel;
        }
    }

    public function getTitle(): string
    {
        return 'Create Contact';
    }

    public function getDescription(): string
    {
        return 'Tool to create a contact in Mautic';
    }

    public function getParameters(): array
    {
        return [
            'email' => [
                'type' => 'string',
                'description' => 'Email address of the contact',
                'required' => false,
            ],
            'name' => [
                'type' => 'string',
                'description' => 'Last name of the contact',
                'required' => false,
            ],
            'firstname' => [
                'type' => 'string',
                'description' => 'First name of the contact',
                'required' => false,
            ],
        ];
    }

    public function execute(array $parameters = []): array
    {
        try {
            // Build contact data from parameters
            $contactData = [];

            if (!empty($parameters['email'])) {
                $contactData['email'] = $parameters['email'];
            }

            if (!empty($parameters['firstname'])) {
                $contactData['firstname'] = $parameters['firstname'];
            }

            if (!empty($parameters['name'])) {
                $contactData['lastname'] = $parameters['name'];
            }

            // Check if we have at least one field to create a contact
            if (empty($contactData)) {
                return [
                    'success' => false,
                    'error' => 'At least one contact field (email, firstname, or name) is required',
                ];
            }

            // Create new contact entity
            $contact = new Lead();

            // Set the contact data
            foreach ($contactData as $field => $value) {
                $contact->addUpdatedField($field, $value);
            }

            // Set additional metadata
            $contact->setDateAdded(new \DateTime());
            $contact->setDateModified(new \DateTime());

            // Get the lead model to save the contact
            if ($this->leadModel) {
                // Save the contact using the model
                $this->leadModel->saveEntity($contact);

                $contactUrl = '/s/contacts/edit/' . $contact->getId();

                return [
                    'success' => true,
                    'message' => 'Contact created successfully with <strong>' . implode(', ', array_values($contactData)) . '</strong>. <a href="' . $contactUrl . '" target="_blank">View contact</a>',
                    'contact_id' => $contact->getId(),
                    'contact_data' => $contactData,
                    'url' => $contactUrl,
                ];
            } else {
                // Fallback if leadModel is not injected
                return [
                    'success' => false,
                    'error' => 'Lead model not available for contact creation',
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