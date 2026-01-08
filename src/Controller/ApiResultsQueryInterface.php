<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

interface ApiResultsQueryInterface
{
    public final const string RUTA_API = '/api/v1/results';

    public function cgetAction(Request $request): Response;
    public function getAction(Request $request, int $resultId): Response;
    public function optionsAction(?int $resultId): Response;
}
