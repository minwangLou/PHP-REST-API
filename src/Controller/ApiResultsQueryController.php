<?php

namespace App\Controller;

use App\Entity\Result;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: ApiResultsQueryInterface::RUTA_API,
    name: 'api_results_'
)]
class ApiResultsQueryController extends AbstractController implements ApiResultsQueryInterface
{
    private const string HEADER_CACHE_CONTROL = 'Cache-Control';
    private const string HEADER_ETAG = 'ETag';
    private const string HEADER_ALLOW = 'Allow';

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route(
        path: '.{_format}',
        name: 'cget',
        requirements: [ '_format' => 'json|xml' ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET ]
    )]
    public function cgetAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $user = $this->getUser();

        $results = $this->isGranted('ROLE_ADMIN')
            ? $this->entityManager->getRepository(Result::class)->findAll()
            : $this->entityManager->getRepository(Result::class)->findBy([ 'user' => $user ]);

        if (empty($results)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $etag = md5(json_encode(array_map(
            fn($r) => [
                'id'    => $r->getId(),
                'value' => $r->getValue(),
                'user'  => $r->getUser()->getId(),
            ],
            $results
        )));

        if (($etags = $request->getETags()) && in_array($etag, $etags, true)) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ 'results' => array_map(fn ($r) => ['result' => $r], $results) ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    #[Route(
        path: '/{resultId}.{_format}',
        name: 'get',
        requirements: [ 'resultId' => '\d+', '_format' => 'json|xml' ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET ]
    )]
    public function getAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        if (!$result instanceof Result) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $user = $this->getUser();

        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $result->getUser()->getId() !== $user->getId()
        ) {
            return Utils::errorMessage(
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN: you don\'t have permission to access',
                $format
            );
        }

        $etag = md5(json_encode([
            'id'    => $result->getId(),
            'value' => $result->getValue(),
            'user'  => $result->getUser()->getId(),
        ]));


        if (
            ($etags = $request->getETags()) &&
            (in_array($etag, $etags, true) || in_array('*', $etags, true))
        ) {
            return (new Response())->setNotModified(); // 304
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            $request->isMethod(Request::METHOD_GET)
                ? [ 'result' => $result ]
                : null,
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    #[Route(
        path: "/{resultId}.{_format}",
        name: 'options',
        requirements: [
            'resultId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ 'resultId' => 0, '_format' => 'json' ],
        methods: [ Request::METHOD_OPTIONS ],
    )]
    public function optionsAction(int|null $resultId): Response
    {
        $methods = $resultId !== 0
            ? [ Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE ]
            : [ Request::METHOD_GET, Request::METHOD_POST ];

        $methods[] = Request::METHOD_OPTIONS;

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                self::HEADER_ALLOW => implode(',', $methods),
                self::HEADER_CACHE_CONTROL => 'public, inmutable'
            ]
        );
    }

    #[Route(
        path: '/stats.{_format}',
        name: 'stats',
        requirements: [ '_format' => 'json|xml' ],
        defaults: [ '_format' => 'json' ],
        methods: [ Request::METHOD_GET ]
    )]
    public function statsAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'COUNT(r.id) AS count',
                'MIN(r.value) AS min',
                'MAX(r.value) AS max',
                'AVG(r.value) AS avg'
            )
            ->from(Result::class, 'r');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->where('r.user = :user')
                ->setParameter('user', $user);
        }

        $stats = $qb->getQuery()->getSingleResult();

        return Utils::apiResponse(
            Response::HTTP_OK,
            [
                'stats' => [
                    'count' => (int) $stats['count'],
                    'min'   => $stats['min'] !== null ? (int) $stats['min'] : null,
                    'max'   => $stats['max'] !== null ? (int) $stats['max'] : null,
                    'avg'   => $stats['avg'] !== null ? (float) $stats['avg'] : null,
                ]
            ],
            $format
        );
    }




}
