<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

interface ApiResultsCommandInterface
{
    public function postAction(Request $request): Response;

    public function putAction(Request $request, int $resultId): Response;

    public function deleteAction(Request $request, int $resultId): Response;
}
