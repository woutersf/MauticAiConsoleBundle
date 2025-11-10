<?php

namespace MauticPlugin\MauticAiConsoleBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticAiConsoleBundle\Entity\AiLog;
use MauticPlugin\MauticAiConsoleBundle\Entity\AiLogRepository;

class AiLogService
{
    private $entityManager;
    private $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(AiLog::class);
    }

    public function createLog($userId, $prompt, $model = null)
    {
        $aiLog = new AiLog();
        $aiLog->setUserId($userId);
        $aiLog->setPrompt($prompt);
        $aiLog->setModel($model);
        $aiLog->setTimestamp(new \DateTime());

        $this->entityManager->persist($aiLog);
        $this->entityManager->flush();

        return $aiLog->getId();
    }

    public function updateLogOutput($logId, $output)
    {
        $aiLog = $this->repository->find($logId);
        if ($aiLog) {
            $aiLog->setOutput($output);
            $this->entityManager->flush();
        }
    }

    public function getLogsByUserId($userId, $limit = 100)
    {
        return $this->repository->getLogsByUserId($userId, $limit);
    }

    public function getRecentLogs($limit = 50)
    {
        return $this->repository->getRecentLogs($limit);
    }

    public function getLog($logId)
    {
        return $this->repository->find($logId);
    }
}