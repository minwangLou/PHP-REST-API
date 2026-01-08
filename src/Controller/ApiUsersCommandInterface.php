<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, Response};

/**
 * Class ApiUsersController
 *
 * @package App\Controller
 *
 */
interface ApiUsersCommandInterface
{
    /**
     * **POST** action<br><br>
     * Summary: Creates a User resource.
     *
     * @param Request $request request
     */
    public function postAction(Request $request): Response;

    /**
     * **PUT** action<br><br>
     * Summary: Updates the User resource.<br>
     * _Notes_: Updates the user identified by <code>userId</code>.
     *
     * @param Request $request request
     * @param int $userId User id
     */
    public function putAction(Request $request, int $userId): Response;

    /**
     * **DELETE** Action<br><br>
     * Summary: Removes the User resource.<br>
     * _Notes_: Deletes the user identified by <code>userId</code>.
     *
     * @param int $userId User id
     */
    public function deleteAction(Request $request, int $userId): Response;
}
