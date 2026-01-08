<?php

namespace App\Controller;

use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

/**
 * Class ApiUsersController
 *
 * @package App\Controller
 */
#[Route(
    path: ApiUsersQueryInterface::RUTA_API,
    name: 'api_users_'
)]
class ApiUsersCommandController extends AbstractController implements ApiUsersCommandInterface
{
    private const string ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * @see ApiUsersCommandInterface::deleteAction()
     */
    #[Route(
        path: "/{userId}.{_format}",
        name: 'delete',
        requirements: [
            'userId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [Request::METHOD_DELETE],
    )]
    public function deleteAction(Request $request, int $userId): Response
    {
        $format = Utils::getFormat($request);
        if ($response = $this->checkAuthUser($format)) {
            return $response;
        }
        // Puede borrar un usuario sólo si tiene ROLE_ADMIN
        if ($response = $this->checkRoleAdmin($format)) {
            return $response;
        }

        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (!$user instanceof User) {   // 404 - Not Found
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * @see ApiUsersCommandInterface::postAction()
     *
     * @throws JsonException
     */
    #[Route(
        path: ".{_format}",
        name: 'post',
        requirements: [
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [Request::METHOD_POST],
    )]
    public function postAction(Request $request): Response
    {
        $format = Utils::getFormat($request);
        if ($response = $this->checkAuthUser($format)) {
            return $response;
        }
        // Puede crear un usuario sólo si tiene ROLE_ADMIN
        if ($response = $this->checkRoleAdmin($format)) {
            return $response;
        }

        $postData = $request->getPayload();
        if (!in_array(User::EMAIL_ATTR, $postData->keys(), true) ||
            !in_array(User::PASSWD_ATTR, $postData->keys(), true) ||
            empty($postData->get(User::EMAIL_ATTR)) ||
            empty($postData->get(User::PASSWD_ATTR))
        ) {
            // 422 - Unprocessable Entity -> Faltan datos
            return Utils::errorMessage(Response::HTTP_UNPROCESSABLE_ENTITY, null, $format);
        }

        // hay datos -> procesarlos
        $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([ User::EMAIL_ATTR => $postData->get(User::EMAIL_ATTR) ]);

        if ($user_exist instanceof User) {    // 400 - Bad Request
            return Utils::errorMessage(Response::HTTP_BAD_REQUEST, null, $format);
        }

        // 201 - Created
        $user = new User(strval($postData->get(User::EMAIL_ATTR)));
        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            strval($postData->get(User::PASSWD_ATTR))
        );
        $user->setPassword($hashedPassword);
        // roles
        (!$postData->has(User::ROLES_ATTR)) ?: $user->setRoles($postData->all()[User::ROLES_ATTR]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ User::USER_ATTR => $user ],
            $format,
            [
                'Location' => $request->getScheme() . '://' . $request->getHttpHost() .
                    ApiUsersQueryInterface::RUTA_API . '/' . $user->getId(),
            ]
        );
    }

    /**
     * @see ApiUsersCommandInterface::putAction()
     *
     * @throws JsonException
     */
    #[Route(
        path: "/{userId}.{_format}",
        name: 'put',
        requirements: [
            'userId' => "\d+",
            '_format' => "json|xml"
        ],
        defaults: [ '_format' => null ],
        methods: [Request::METHOD_PUT],
    )]
    public function putAction(Request $request, int $userId): Response
    {
        $format = Utils::getFormat($request);
        if ($response = $this->checkAuthUser($format)) {
            return $response;
        }

        // Puede editar otro usuario diferente sólo si tiene ROLE_ADMIN
        /** @var User $user */
        $user = $this->getUser();
        if (($user->getId() !== $userId) && ($response = $this->checkRoleAdmin($format))) {
                return $response;
        }

        $postData = $request->getPayload();

        /** @var User $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (!$user instanceof User) {    // 404 - Not Found
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }

        // Optimistic Locking (strong validation, password included)
        $etag = md5(json_encode($user, JSON_THROW_ON_ERROR) . $user->getPassword());
        if (!$request->headers->has('If-Match') || $etag != $request->headers->get('If-Match')) {
            return Utils::errorMessage(
                Response::HTTP_PRECONDITION_FAILED,
                'PRECONDITION FAILED: one or more conditions given evaluated to false',
                $format
            ); // 412
        }

        // email
        if ($postData->has(User::EMAIL_ATTR)) {
            $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([ User::EMAIL_ATTR => $postData->get(User::EMAIL_ATTR) ]);

            if ($user_exist instanceof User) {    // 400 - Bad Request
                return Utils::errorMessage(Response::HTTP_BAD_REQUEST, null, $format);
            }
            $user->setEmail($postData->get(User::EMAIL_ATTR));
        }

        // password
        if ($postData->has(User::PASSWD_ATTR)) {
            // hash the password (based on the security.yaml config for the $user class)
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $postData->get(User::PASSWD_ATTR)
            );
            $user->setPassword($hashedPassword);
        }

        // roles
        if ($postData->has(User::ROLES_ATTR) && is_array($postData->all()[User::ROLES_ATTR])) {
            if (
                in_array(self::ROLE_ADMIN, $postData->all()[User::ROLES_ATTR], true)
                && !$this->isGranted(self::ROLE_ADMIN)
            ) {
                return Utils::errorMessage( // 403
                    Response::HTTP_FORBIDDEN,
                    'FORBIDDEN: you don\'t have permission to access',
                    $format
                );
            }
            $user->setRoles($postData->all()[User::ROLES_ATTR]);
        }

        $this->entityManager->flush();

        return Utils::apiResponse(
            209,                        // 209 - Content Returned
            [ User::USER_ATTR => $user ],
            $format
        );
    }

    private function checkAuthUser(string $format): ?Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage( // 401
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHORIZED: Invalid credentials.',
                $format
            );
        }

        return null;
    }

    private function checkRoleAdmin(string $format): ?Response
    {
        if (!$this->isGranted(self::ROLE_ADMIN)) {
            return Utils::errorMessage( // 403
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN: you don\'t have permission to access',
                $format
            );
        }

        return null;
    }
}
