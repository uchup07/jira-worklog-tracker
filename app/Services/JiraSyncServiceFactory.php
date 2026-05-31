<?php

namespace App\Services;

class JiraSyncServiceFactory
{
    public function make(): JiraSyncService
    {
        return JiraSyncService::make();
    }
}
