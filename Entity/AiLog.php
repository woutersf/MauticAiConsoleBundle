<?php

namespace MauticPlugin\MauticAiConsoleBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

class AiLog
{
    public const TABLE_NAME = 'ai_log';

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var \DateTime
     */
    private $timestamp;

    /**
     * @var string
     */
    private $prompt;

    /**
     * @var string
     */
    private $model;

    /**
     * @var string
     */
    private $output;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable(self::TABLE_NAME);
        $builder->setCustomRepositoryClass(AiLogRepository::class);

        $builder->addId();

        $builder->createField('userId', 'integer')
            ->columnName('user_id')
            ->nullable()
            ->build();

        $builder->createField('timestamp', 'datetime')
            ->columnName('timestamp')
            ->build();

        $builder->createField('prompt', 'text')
            ->columnName('prompt')
            ->build();

        $builder->createField('model', 'string')
            ->columnName('model')
            ->length(255)
            ->nullable()
            ->build();

        $builder->createField('output', 'text')
            ->columnName('output')
            ->nullable()
            ->build();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTime $timestamp)
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getPrompt()
    {
        return $this->prompt;
    }

    public function setPrompt($prompt)
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }
}