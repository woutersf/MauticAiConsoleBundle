<?php

namespace MauticPlugin\MauticAiConsoleBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticAiConsoleBundle\Entity\AiLog;
use MauticPlugin\MauticAIconnectionBundle\Service\LiteLLMService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ConsoleController extends CommonController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'mautic.ai_connection.service.litellm' => LiteLLMService::class,
        ]);
    }

    public function postActionRedirect($args = [])
    {
        // Disable post action redirect for streaming responses
        return null;
    }

    public function processAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => $this->translator->trans('mautic.ai_console.error.invalid_request')], 400);
        }

        $input = $request->request->get('input');
        if (empty($input)) {
            return new JsonResponse(['error' => $this->translator->trans('mautic.ai_console.error.no_input')], 400);
        }

        // Get current user
        $user = $this->getUser();
        $userId = $user ? $user->getId() : null;

        try {
            // Get LiteLLM service
            $liteLLMService = $this->container->get('mautic.ai_connection.service.litellm');

            // Get configuration from core parameters
            $coreParametersHelper = $this->factory->getHelper('core_parameters');
            $aiModel = $coreParametersHelper->get('ai_console_model', 'gpt-3.5-turbo');
            $prePrompt = $coreParametersHelper->get('pre_prompt', 'You are a helpful AI assistant integrated into Mautic.');

            // Get user's locale, name, email, and Mautic version, replace tokens in pre-prompt
            $userLocale = 'English'; // Default fallback
            $userFirstName = 'User'; // Default fallback
            $userLastName = ''; // Default fallback
            $userEmail = ''; // Default fallback
            $mauticVersion = 'Unknown'; // Default fallback

            // Get Mautic version
            try {
                $appVersionHelper = $this->factory->getHelper('app_version');
                if ($appVersionHelper && method_exists($appVersionHelper, 'getVersion')) {
                    $mauticVersion = $appVersionHelper->getVersion();
                }
            } catch (\Exception $e) {
                // Keep default fallback value
            }

            if ($user) {
                // Get user's locale
                if (method_exists($user, 'getLocale')) {
                    $locale = $user->getLocale();
                    if ($locale) {
                        // Convert locale code to readable language name
                        $userLocale = $this->getLanguageFromLocale($locale);
                    }
                }

                // Get user's first name
                if (method_exists($user, 'getFirstName')) {
                    $firstName = $user->getFirstName();
                    if (!empty($firstName)) {
                        $userFirstName = $firstName;
                    }
                }

                // Get user's last name
                if (method_exists($user, 'getLastName')) {
                    $lastName = $user->getLastName();
                    if (!empty($lastName)) {
                        $userLastName = $lastName;
                    }
                }

                // Get user's email
                if (method_exists($user, 'getEmail')) {
                    $email = $user->getEmail();
                    if (!empty($email)) {
                        $userEmail = $email;
                    }
                }
            }

            // Replace all tokens in pre-prompt
            $prePrompt = str_replace(['{language}', '{account_firstname}', '{account_lastname}', '{account_email}', '{mautic_version}'],
                                   [$userLocale, $userFirstName, $userLastName, $userEmail, $mauticVersion],
                                   $prePrompt);

            // Create initial log entry using entity manager directly
            $entityManager = $this->getDoctrine()->getManager();
            $aiLog = new AiLog();
            $aiLog->setUserId($userId);
            $aiLog->setPrompt($input);
            $aiLog->setModel($aiModel);
            $aiLog->setTimestamp(new \DateTime());

            $entityManager->persist($aiLog);
            $entityManager->flush();

            $logId = $aiLog->getId();

            // Fetch last conversation entries for context
            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder
                ->select('a')
                ->from(AiLog::class, 'a')
                ->where('a.userId = :userId')
                ->andWhere('a.output IS NOT NULL') // Only completed conversations
                ->setParameter('userId', $userId)
                ->orderBy('a.timestamp', 'DESC')
                ->setMaxResults(7);

            $recentLogs = $queryBuilder->getQuery()->getResult();

            // Prepare the API request with conversation history
            $messages = [
                [
                    'role' => 'system',
                    'content' => $prePrompt
                ]
            ];

            // Add recent conversation history (reverse order for chronological)
            foreach (array_reverse($recentLogs) as $log) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $log->getPrompt()
                ];
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $log->getOutput()
                ];
            }

            // Add current user input
            $messages[] = [
                'role' => 'user',
                'content' => $input
            ];

            // Detect and add available tools
            $tools = $this->getAvailableTools($coreParametersHelper);

            // Generate Mautic instance fingerprint
            $mauticFingerprint = $this->generateMauticFingerprint($coreParametersHelper);

            // Build options for LiteLLM service
            $options = [
                'model' => $aiModel,
                'temperature' => 0.3,
                'mautic_fingerprint' => $mauticFingerprint,
            ];

            // Add tools if any are available
            if (!empty($tools)) {
                $options['tools'] = $tools;
                $options['tool_choice'] = 'auto';
            }

            // Make API call using LiteLLM service
            $responseData = $liteLLMService->getChatCompletion($messages, $options);

            if (!isset($responseData['choices'][0]['message'])) {
                throw new \Exception($this->translator->trans('mautic.ai_console.error.invalid_api_response', ['%data%' => json_encode($responseData)]));
            }

            $message = $responseData['choices'][0]['message'];

            // Check if the response contains tool calls
            if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                $response = $this->handleToolCalls($message['tool_calls']);
            } else if (isset($message['content']) && !empty($message['content'])) {
                $response = $message['content'];
            } else {
                throw new \Exception($this->translator->trans('mautic.ai_console.error.invalid_api_response', ['%data%' => json_encode($responseData)]));
            }

            // Update log with response
            $aiLog->setOutput($response);
            $entityManager->persist($aiLog);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'response' => $response,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function historyAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => $this->translator->trans('mautic.ai_console.error.invalid_request')], 400);
        }

        try {
            $entityManager = $this->getDoctrine()->getManager();

            // Get current user
            $user = $this->getUser();
            $userId = $user ? $user->getId() : null;

            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder
                ->select('a')
                ->from(AiLog::class, 'a')
                ->where('a.userId = :userId')
                ->setParameter('userId', $userId)
                ->orderBy('a.timestamp', 'DESC')
                ->setMaxResults(50);

            $logs = $queryBuilder->getQuery()->getResult();

            $history = [];
            foreach ($logs as $log) {
                if ($log->getPrompt() && $log->getOutput()) {
                    $history[] = [
                        'prompt' => $log->getPrompt(),
                        'output' => $log->getOutput(),
                        'timestamp' => $log->getTimestamp()->format('Y-m-d H:i:s')
                    ];
                }
            }

            // Reverse to show oldest first (chronological order)
            $history = array_reverse($history);

            return new JsonResponse([
                'success' => true,
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getAvailableTools($coreParametersHelper): array
    {
        $tools = [];
        $toolsDirectory = __DIR__ . '/../Tools';

        // Check if Tools directory exists
        if (!is_dir($toolsDirectory)) {
            return $tools;
        }

        // Scan for tool classes
        $toolFiles = glob($toolsDirectory . '/*Tool.php');

        foreach ($toolFiles as $toolFile) {
            $className = basename($toolFile, '.php');
            $fullClassName = "MauticPlugin\\MauticAiConsoleBundle\\Tools\\{$className}";

            // Check if class exists
            if (!class_exists($fullClassName)) {
                continue;
            }

            // Check if tool is enabled in configuration
            $configKey = 'tool_' . strtolower(str_replace('\\', '_', $fullClassName));
            if (!$coreParametersHelper->get($configKey, false)) {
                continue; // Tool not enabled
            }

            try {
                // Instantiate tool to get its metadata with proper dependencies
                $toolInstance = $this->createToolInstance($fullClassName);

                // Build tool definition for API
                $toolDefinition = [
                    'type' => 'function',
                    'function' => [
                        'name' => $this->getToolFunctionName($className),
                        'description' => $toolInstance->getDescription(),
                    ]
                ];

                // Add parameters
                $parameters = $toolInstance->getParameters();
                if(!empty($parameters)) {
                    $toolDefinition['function']['parameters'] = [
                        'type' => 'object',
                        'properties' => [],
                        'required' => []
                    ];

                    foreach ($parameters as $paramName => $paramConfig) {
                        $toolDefinition['function']['parameters']['properties'][$paramName] = [
                            'type' => $paramConfig['type'],
                            'description' => $paramConfig['description']
                        ];

                        if ($paramConfig['required']) {
                            $toolDefinition['function']['parameters']['required'][] = $paramName;
                        }
                    }
                }

                $tools[] = $toolDefinition;

            } catch (\Exception $e) {
                // Skip tools that can't be instantiated
                continue;
            }
        }

        return $tools;
    }

    private function getToolFunctionName(string $className): string
    {
        // Convert CreateContactTool -> create_contact
        $name = preg_replace('/Tool$/', '', $className);
        $name = preg_replace('/([A-Z])/', '_$1', $name);
        $name = ltrim($name, '_');
        return strtolower($name);
    }

    private function handleToolCalls(array $toolCalls): string
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            if ($toolCall['type'] !== 'function') {
                continue;
            }

            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);

            try {
                $result = $this->executeTool($functionName, $arguments);
                $results[] = $this->formatToolResult($functionName, $result);
            } catch (\Exception $e) {
                $results[] = "Error executing {$functionName}: " . $e->getMessage();
            }
        }

        return implode("\n\n", $results);
    }

    private function executeTool(string $functionName, array $arguments): array
    {
        // Convert function name back to class name (create_segment -> CreateSegmentTool)
        $className = $this->getToolClassName($functionName);
        $fullClassName = "MauticPlugin\\MauticAiConsoleBundle\\Tools\\{$className}";

        if (!class_exists($fullClassName)) {
            throw new \Exception($this->translator->trans('mautic.ai_console.error.tool_not_found', ['%className%' => $className]));
        }

        // Inject appropriate models based on tool type
        $toolInstance = $this->createToolInstance($fullClassName);
        return $toolInstance->execute($arguments);
    }

    private function createToolInstance(string $fullClassName)
    {
        // Check tool type and inject required dependencies
        if (strpos($fullClassName, 'CreateContactTool') !== false) {
            $leadModel = $this->factory->getModel('lead');
            return new $fullClassName($leadModel, $this->translator);
        } else if (strpos($fullClassName, 'CreateSegmentTool') !== false) {
            $listModel = $this->factory->getModel('lead.list');
            return new $fullClassName($listModel, $this->translator);
        } else if (strpos($fullClassName, 'ClearCacheTool') !== false) {
            $cacheHelper = $this->factory->getHelper('cache');
            return new $fullClassName($cacheHelper, $this->translator);
        } else {
            // Default instantiation without dependencies
            return new $fullClassName(null, $this->translator);
        }
    }

    private function getToolClassName(string $functionName): string
    {
        // Convert create_segment -> CreateSegmentTool
        $parts = explode('_', $functionName);
        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }
        return $className . 'Tool';
    }

    private function formatToolResult(string $functionName, array $result): string
    {
        if ($result['success']) {
            return "✅ **" . $functionName . "** executed successfully: " . $result['message'];
        } else {
            return $this->translator->trans('mautic.ai_console.tool.failed', [
                '%tool%' => $functionName,
                '%error%' => $result['error'] ?? $this->translator->trans('mautic.ai_console.error.unknown')
            ]);
        }
    }

    private function getLanguageFromLocale(string $locale): string
    {
        // Common locale to language mappings
        $languageMap = [
            'en' => 'English',
            'en_US' => 'English',
            'en_GB' => 'English',
            'fr' => 'French',
            'fr_FR' => 'French',
            'de' => 'German',
            'de_DE' => 'German',
            'es' => 'Spanish',
            'es_ES' => 'Spanish',
            'it' => 'Italian',
            'it_IT' => 'Italian',
            'pt' => 'Portuguese',
            'pt_BR' => 'Portuguese',
            'pt_PT' => 'Portuguese',
            'nl' => 'Dutch',
            'nl_NL' => 'Dutch',
            'ru' => 'Russian',
            'ru_RU' => 'Russian',
            'ja' => 'Japanese',
            'ja_JP' => 'Japanese',
            'zh' => 'Chinese',
            'zh_CN' => 'Chinese',
            'zh_TW' => 'Chinese',
            'ko' => 'Korean',
            'ko_KR' => 'Korean',
            'ar' => 'Arabic',
            'ar_SA' => 'Arabic',
            'pl' => 'Polish',
            'pl_PL' => 'Polish',
            'sv' => 'Swedish',
            'sv_SE' => 'Swedish',
            'da' => 'Danish',
            'da_DK' => 'Danish',
            'no' => 'Norwegian',
            'no_NO' => 'Norwegian',
            'fi' => 'Finnish',
            'fi_FI' => 'Finnish',
        ];

        // Check exact match first
        if (isset($languageMap[$locale])) {
            return $languageMap[$locale];
        }

        // Check base language (e.g., 'fr' from 'fr_CA')
        $baseLang = explode('_', $locale)[0];
        if (isset($languageMap[$baseLang])) {
            return $languageMap[$baseLang];
        }

        // Fallback to English if locale not found
        return 'English';
    }

    /**
     * Generate a unique fingerprint for this Mautic instance
     * This helps identify if multiple Mautic instances are using the same AI key
     */
    private function generateMauticFingerprint($coreParametersHelper): string
    {
        // Gather system-specific information
        $fingerprintData = [];

        // Always add server hostname
        $fingerprintData[] = php_uname('n');

        // Add site URL if available
        $siteUrl = $coreParametersHelper->get('site_url', '');
        if (!empty($siteUrl)) {
            $fingerprintData[] = $siteUrl;
        }

        // Add database configuration (without sensitive data)
        $dbHost = $coreParametersHelper->get('db_host', '');
        $dbName = $coreParametersHelper->get('db_name', '');
        if (!empty($dbHost) && !empty($dbName)) {
            $fingerprintData[] = $dbHost . ':' . $dbName;
        }

        // Add secret key (if available) to make fingerprint unique
        $secretKey = $coreParametersHelper->get('secret_key', '');
        if (!empty($secretKey)) {
            $fingerprintData[] = $secretKey;
        }

        // Generate SHA-256 hash of the collected data
        $fingerprintString = implode('|', $fingerprintData);
        return hash('sha256', $fingerprintString);
    }

    public function speechToTextAction(Request $request)
    {
        // Handle HEAD request for config check
        if ($request->getMethod() === 'HEAD') {
            $coreParametersHelper = $this->factory->getHelper('core_parameters');
            $speechEnabled = $coreParametersHelper->get('speech_to_text_enabled', false);

            if ($speechEnabled) {
                return new JsonResponse(['enabled' => true]);
            } else {
                return new JsonResponse(['enabled' => false], 404);
            }
        }

        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Invalid request'], 400);
        }

        try {
            // Get LiteLLM service
            $liteLLMService = $this->container->get('mautic.ai_connection.service.litellm');

            $coreParametersHelper = $this->factory->getHelper('core_parameters');

            // Check if speech-to-text is enabled
            if (!$coreParametersHelper->get('speech_to_text_enabled', false)) {
                return new JsonResponse(['error' => 'Speech-to-text is not enabled'], 403);
            }

            // Check if audio file was uploaded
            $audioFile = $request->files->get('audio');
            if (!$audioFile) {
                return new JsonResponse(['error' => 'No audio file provided'], 400);
            }

            // Get configuration
            $speechModel = $coreParametersHelper->get('speech_to_text_model', 'whisper-1');

            // Prepare the file for upload to LiteLLM
            $audioData = file_get_contents($audioFile->getPathname());

            // Get user's language for speech recognition
            $user = $this->getUser();
            $userLanguage = 'auto'; // Default to auto-detection
            if ($user) {
                $locale = $user->getLocale();
                if ($locale) {
                    // Convert locale to language code for Whisper (e.g., en_US -> en, fr_FR -> fr)
                    $languageCode = explode('_', $locale)[0];
                    $userLanguage = $languageCode;
                }
            }

            // Generate Mautic instance fingerprint
            $mauticFingerprint = $this->generateMauticFingerprint($coreParametersHelper);

            // Call speech-to-text service
            $transcribedText = $liteLLMService->speechToText($audioData, $userLanguage, $speechModel, $mauticFingerprint);

            return new JsonResponse([
                'success' => true,
                'text' => $transcribedText
            ]);

        } catch (\Exception $e) {
            error_log('MauticAiConsole: Speech-to-text error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Speech-to-text processing failed'], 500);
        }
    }
}