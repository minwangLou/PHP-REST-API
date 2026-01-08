<?php

namespace App\Controller;

use App\Entity\Result;
use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: ApiResultsQueryInterface::RUTA_API,
    name: 'api_results_'
)]
class ApiResultsCommandController extends AbstractController
{
    private const string ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }


    #[Route(
        path: '.{_format}',
        name: 'post',
        requirements: [ '_format' => 'json|xml' ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_POST ],
    )]
    public function postAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $postData = $request->getPayload();

        if (!$postData->has('value')) {
            return Utils::errorMessage(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                null,
                $format
            );
        }

        $authUser = $this->getUser();

        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($authUser->getId());

        $result = new Result((int) $postData->get('value'));
        $result->setUser($user);

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ 'result' => $result ],
            $format,
            [
                'Location' =>
                    $request->getScheme() . '://' .
                    $request->getHttpHost() .
                    ApiResultsQueryInterface::RUTA_API . '/' .
                    $result->getId(),
            ]
        );
    }


    #[Route(
        path: "/{resultId}.{_format}",
        name: 'delete',
        requirements: [
            'resultId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [Request::METHOD_DELETE],
    )]
    public function deleteAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);
        if ($response = $this->checkAuthUser($format)) {
            return $response;
        }

        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        if (!$result instanceof Result) {   // 404 - Not Found
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        if (!$this->isGranted(self::ROLE_ADMIN)) {
            $authUser = $this->getUser();
            if ($authUser !== $result->getUser()) {
                return Utils::errorMessage( // 403
                    Response::HTTP_FORBIDDEN,
                    'FORBIDDEN: you don\'t have permission to access',
                    $format
                );
            }
        }

        $this->entityManager->remove($result);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }



    /**
     * Updates a Result resource
     */
    #[Route(
        path: "/{resultId}.{_format}",
        name: 'put',
        requirements: [
            'resultId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [ Request::METHOD_PUT ],
    )]
    public function putAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

        if ($response = $this->checkAuthUser($format)) {
            return $response;
        }

        $postData = $request->getPayload();

        /** @var Result $result */
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        if (!$result instanceof Result) {   // 404
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        // Ownership check (admin OR owner)
        if (!$this->isGranted(self::ROLE_ADMIN)) {
            $authUser = $this->getUser();
            if ($result->getUser()->getId() !== $authUser->getId()) {
                return Utils::errorMessage(
                    Response::HTTP_FORBIDDEN,
                    'FORBIDDEN: you don\'t have permission to access',
                    $format
                );
            }
        }

        // Etag
        $etag = md5(json_encode([
            'id'    => $result->getId(),
            'value' => $result->getValue(),
            'user'  => $result->getUser()->getId(),
        ]));


        if (
            !$request->headers->has('If-Match')
            || $etag !== $request->headers->get('If-Match')
        ) {
            return Utils::errorMessage(
                Response::HTTP_PRECONDITION_FAILED,
                'PRECONDITION FAILED',
                $format
            );
        }

        // value
        if (!$postData->has('value')) {   // 422
            return Utils::errorMessage(Response::HTTP_UNPROCESSABLE_ENTITY, null, $format);
        }

        $result->setValue((int) $postData->get('value'));

        $this->entityManager->flush();

        return Utils::apiResponse(
            209, // Content Returned
            [ 'result' => $result ],
            $format
        );
    }




    private function checkAuthUser(string $format): ?Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        return null;
    }

}
