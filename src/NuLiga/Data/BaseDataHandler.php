<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

use ContaoBayern\NuligadataBundle\NuLiga\Request\AuthenticatedRequest;
use RuntimeException;

class BaseDataHandler
{
    /**
     * @var AuthenticatedRequest
     */
    protected $authenticatedRequest;

    public function __construct(AuthenticatedRequest $authenticatedRequest)
    {
        $this->authenticatedRequest = $authenticatedRequest;
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
