<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

use ContaoBayern\NuligadataBundle\NuLiga\Request\AuthenticatedRequest;
use Monolog\Logger;
use RuntimeException;

class BaseDataHandler
{
    /**
     * @var AuthenticatedRequest
     */
    protected $authenticatedRequest;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(AuthenticatedRequest $authenticatedRequest, Logger $logger)
    {
        $this->authenticatedRequest = $authenticatedRequest;
        $this->logger = $logger;
    }

    /**
     * @throws RuntimeException
     */
    public function prepareRequest(): void
    {
        if (!$this->authenticatedRequest->authenticate()) {
            throw new RuntimeException('konnte nicht authentifizieren');
        }
    }

}
