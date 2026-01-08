<?php

/**
 * @license https://opensource.org/licenses/MIT MIT License
 * @link    https://www.etsisi.upm.es/ ETS de Ingeniería de Sistemas Informáticos
 */

namespace App\Controller;

use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class DefaultController
 *
 * @package App\Controller
 */
class DefaultController
{
    /**
     * Redirect home page
     */
    #[Route(
        path: '/',
        name: 'homepage',
        methods: [ Request::METHOD_GET ],
    )]
    public function homeRedirect(): RedirectResponse
    {
        return new RedirectResponse(
            '/api-docs/index.html',
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
