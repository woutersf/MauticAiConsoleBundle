<?php

namespace MauticPlugin\MauticAiConsoleBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\MauticAIconnectionBundle\Service\LiteLLMService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigType extends AbstractType
{
    private $liteLLMService;

    public function __construct(LiteLLMService $liteLLMService)
    {
        $this->liteLLMService = $liteLLMService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'ai_console_enabled',
            YesNoButtonGroupType::class,
            [
                'label'      => 'mautic.ai_console.config.enabled',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'tooltip' => 'mautic.ai_console.config.enabled.tooltip',
                ],
                'data'       => $options['data']['ai_console_enabled'] ?? false,
                'required'   => false,
            ]
        );

        // Fetch available models from LLM endpoint (via AI Connection service)
        $modelChoices = $this->getModelChoices();

        $builder->add(
            'ai_console_model',
            ChoiceType::class,
            [
                'choices'     => $modelChoices,
                'label'       => 'mautic.ai_console.config.ai.model',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.ai_console.config.ai.model.tooltip',
                ],
                'required'    => false,
                'placeholder' => 'mautic.ai_console.config.ai.model.placeholder',
            ]
        );

        $builder->add(
            'pre_prompt',
            TextareaType::class,
            [
                'label'      => 'mautic.ai_console.config.pre_prompt',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'tooltip'     => 'mautic.ai_console.config.pre_prompt.tooltip',
                    'rows'        => 4,
                    'placeholder' => 'Enter system instructions that will be prepended to all user prompts. Available tokens: {language}, {account_firstname}, {account_lastname}, {account_email}, {mautic_version}',
                ],
                'required'   => false,
                'data'       => $options['data']['pre_prompt'] ?? 'You are a helpful AI assistant integrated into Mautic. Hello {account_firstname}, I will help you with your marketing automation tasks, email campaigns, and contact management. Please respond in {language}.',
                'help'       => 'Available tokens: {language} (user\'s language), {account_firstname} (user\'s first name), {account_lastname} (user\'s last name), {account_email} (user\'s email), {mautic_version} (Mautic version). Example: "Hello {account_firstname}, you are using Mautic {mautic_version}. Please respond in {language}."',
            ]
        );

        // Speech-to-Text Configuration
        $builder->add(
            'speech_to_text_enabled',
            YesNoButtonGroupType::class,
            [
                'label'      => 'mautic.ai_console.config.speech_to_text.enabled',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'tooltip' => 'mautic.ai_console.config.speech_to_text.enabled.tooltip',
                ],
                'data'       => $options['data']['speech_to_text_enabled'] ?? false,
                'required'   => false,
            ]
        );

        // Speech-to-Text Model Selection (filtered for speech models)
        $speechModelChoices = $this->getSpeechModelChoices();

        $builder->add(
            'speech_to_text_model',
            ChoiceType::class,
            [
                'choices'     => $speechModelChoices,
                'label'       => 'mautic.ai_console.config.speech_to_text.model',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.ai_console.config.speech_to_text.model.tooltip',
                ],
                'required'    => false,
                'placeholder' => 'mautic.ai_console.config.speech_to_text.model.placeholder',
            ]
        );

        // Add tools section
        $availableTools = $this->getAvailableTools();
        foreach ($availableTools as $toolClass => $toolInfo) {
            $toolKey = 'tool_' . strtolower(str_replace('\\', '_', $toolClass));
            $builder->add(
                $toolKey,
                YesNoButtonGroupType::class,
                [
                    'label'      => $toolInfo['title'],
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'tooltip' => $toolInfo['description'],
                    ],
                    'data'       => $options['data'][$toolKey] ?? false,
                    'required'   => false,
                ]
            );
        }
    }

    public function getBlockPrefix(): string
    {
        return 'aiconsoleconfig';
    }

    private function getModelChoices(): array
    {
        try {
            // Fetch available models from LiteLLM service
            return $this->liteLLMService->getAvailableModels();
        } catch (\Exception $e) {
            // Return empty array if service is not configured or fails
            return [];
        }
    }

    private function getSpeechModelChoices(): array
    {
        try {
            // Fetch all available models from LiteLLM service
            $allModels = $this->liteLLMService->getAvailableModels();

            // Filter for speech-to-text models
            $speechModels = [];
            foreach ($allModels as $modelName => $modelId) {
                // Filter models that contain speech/whisper/audio keywords
                if (stripos($modelId, 'whisper') !== false ||
                    stripos($modelId, 'speech') !== false ||
                    stripos($modelId, 'audio') !== false ||
                    stripos($modelId, 'asr') !== false ||
                    stripos($modelName, 'whisper') !== false ||
                    stripos($modelName, 'speech') !== false) {
                    $speechModels[$modelName] = $modelId;
                }
            }

            // If no speech models found, return all models as they may support transcription
            if (empty($speechModels) && !empty($allModels)) {
                return $allModels;
            }

            return $speechModels;
        } catch (\Exception $e) {
            // Return empty array if service is not configured or fails
            return [];
        }
    }

    private function getAvailableTools(): array
    {
        // Auto-detect tool classes
        $tools = [];
        $toolsPath = __DIR__ . '/../../Tools';

        if (is_dir($toolsPath)) {
            $files = glob($toolsPath . '/*Tool.php');
            foreach ($files as $file) {
                $className = 'MauticPlugin\\MauticAiConsoleBundle\\Tools\\' . basename($file, '.php');
                if (class_exists($className)) {
                    try {
                        $instance = new $className();
                        if (method_exists($instance, 'getTitle') &&
                            method_exists($instance, 'getDescription') &&
                            method_exists($instance, 'execute')) {
                            $tools[$className] = [
                                'title' => $instance->getTitle(),
                                'description' => $instance->getDescription(),
                            ];
                        }
                    } catch (\Exception $e) {
                        // Skip tools that can't be instantiated
                    }
                }
            }
        }

        return $tools;
    }
}