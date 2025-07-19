<?php

namespace Skeylup\LaravelPipedrive\Contracts;

use Devio\Pipedrive\PipedriveToken;
use Devio\Pipedrive\PipedriveTokenStorage;

interface PipedriveTokenStorageInterface extends PipedriveTokenStorage
{
    /**
     * Store the Pipedrive token
     */
    public function setToken(PipedriveToken $token): void;

    /**
     * Retrieve the stored Pipedrive token
     */
    public function getToken(): ?PipedriveToken;
}
