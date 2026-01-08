<?php

namespace App\Tests\Controller;

use App\Entity\Result;
use App\Entity\User;
use Generator;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Depends, Group};
use Symfony\Component\HttpFoundation\{Request, Response};

#[Group('controllers')]
#[CoversClass(\App\Controller\ApiResultsQueryController::class)]
#[CoversClass(\App\Controller\ApiResultsCommandController::class)]
class ApiResultsControllerTest extends BaseTestCase
{
    private const string RUTA_API = '/api/v1/results';

    /** @var array<string,string> */
    private static array $adminHeaders;

    /** @var array<string,string> */
    private static array $userHeaders;

    /**
     * Test OPTIONS /results[/resultId] 204 No Content
     */
    public function testOptionsResultAction204NoContent(): void
    {
        // OPTIONS collection
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNotEmpty($response->headers->get('Allow'));

        // OPTIONS item
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API . '/1'
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNotEmpty($response->headers->get('Allow'));
    }

    /**
     * Test POST /results 201 Created
     *
     * @return array<string,mixed> result data
     */
    public function testPostResultAction201Created(): array
    {
        // login as ROLE_USER
        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        $postData = [
            'value' => 100
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$userHeaders,
            json_encode($postData)
        );

        $response = self::$client->getResponse();

        // 201 Created
        self::assertSame(
            Response::HTTP_CREATED,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
        self::assertTrue($response->isSuccessful());

        // Location header
        self::assertNotNull($response->headers->get('Location'));

        // Body
        self::assertJson((string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true);

        self::assertArrayHasKey('result', $body);

        $result = $body['result'];

        self::assertNotEmpty($result['id']);
        self::assertSame($postData['value'], $result['value']);

        return $result;
    }

    /**
     * Test GET /results 200 OK
     *
     * @return string ETag header
     */
    #[Depends('testPostResultAction201Created')]
    public function testCGetResultsAction200Ok(): string
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API,
            [],
            [],
            self::$userHeaders
        );

        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());

        $body = (string) $response->getContent();
        self::assertJson($body);

        $data = json_decode($body, true);
        self::assertArrayHasKey('results', $data);
        self::assertNotEmpty($data['results']);

        return (string) $response->getEtag();
    }


    /**
     * Test GET /results 304 NOT MODIFIED
     *
     * @param string $etag returned by testCGetResultsAction200Ok
     */
    #[Depends('testCGetResultsAction200Ok')]
    public function testCGetResultsAction304NotModified(string $etag): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API,
            [],
            [],
            array_merge(
                self::$userHeaders,
                ['HTTP_If-None-Match' => [$etag]]
            )
        );

        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NOT_MODIFIED,
            $response->getStatusCode()
        );
    }


    /**
     * Test GET /results/{resultId} 200 OK
     *
     * @param array<string,mixed> $result returned by testPostResultAction201Created
     * @return string ETag header
     */
    #[Depends('testPostResultAction201Created')]
    public function testGetResultAction200Ok(array $result): string
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$userHeaders
        );

        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
        self::assertNotNull($response->getEtag());

        $body = (string) $response->getContent();
        self::assertJson($body);

        $data = json_decode($body, true);
        self::assertArrayHasKey('result', $data);
        self::assertSame($result['id'], $data['result']['id']);

        return (string) $response->getEtag();
    }


    /**
     * Test GET /results/{id} 304 NOT MODIFIED
     *
     * @param array<string,mixed> $result
     * @param string $etag
     */
    #[Depends('testPostResultAction201Created')]
    #[Depends('testGetResultAction200Ok')]
    public function testGetResultAction304NotModified(array $result, string $etag): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$userHeaders,
                ['HTTP_If-None-Match' => [$etag]]
            )
        );

        self::assertSame(
            Response::HTTP_NOT_MODIFIED,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Test PUT /results/{resultId} 209 Content Returned
     *
     * @param array<string,mixed> $result returned by testPostResultAction201Created
     * @param string $etag returned by testGetResultAction200Ok
     * @return array<string,mixed> updated result
     */
    #[Depends('testPostResultAction201Created')]
    #[Depends('testGetResultAction200Ok')]
    public function testPutResultAction209ContentReturned(array $result, string $etag): array
    {
        $putData = [
            'value' => 999
        ];

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$userHeaders,
                ['HTTP_If-Match' => $etag]
            ),
            json_encode($putData)
        );

        $response = self::$client->getResponse();

        self::assertSame(
            209,
            $response->getStatusCode(),
            (string) $response->getContent()
        );

        self::assertJson((string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true);

        self::assertSame(
            $putData['value'],
            $body['result']['value']
        );

        return $body['result'];
    }


    /**
     * Test PUT /results/{resultId} 412 PRECONDITION FAILED
     *
     * @param array<string,mixed> $result
     */
    #[Depends('testPostResultAction201Created')]
    public function testPutResultAction412PreconditionFailed(array $result): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$userHeaders
        );

        self::assertSame(
            Response::HTTP_PRECONDITION_FAILED,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Test PUT /results/{resultId} 422 UNPROCESSABLE ENTITY
     *
     * @param array<string,mixed> $result
     */
    #[Depends('testPostResultAction201Created')]
    #[Depends('testGetResultAction200Ok')]
    public function testPutResultAction422UnprocessableEntity(array $result): void
    {
        // Get a valid ETag using GET (ResultController has no HEAD)
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$userHeaders
        );
        $etag = self::$client->getResponse()->getEtag();

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$userHeaders,
                ['HTTP_If-Match' => $etag]
            ),
            json_encode([]) // missing value
        );

        self::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Create a Result as ADMIN (for 403 tests)
     *
     * @return array<string,mixed>
     */
    public function testPostResultAsAdmin201Created(): array
    {
        self::$adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        $postData = [
            'value' => 777
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            json_encode($postData)
        );

        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);

        return $body['result'];
    }


    /**
     * Test GET /results/{id} 403 FORBIDDEN (not owner)
     *
     * @param array<string,mixed> $result created by admin
     */
    #[Depends('testPostResultAsAdmin201Created')]
    public function testGetResultAction403Forbidden(array $result): void
    {
        // login as normal user
        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$userHeaders
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Test PUT /results/{id} 403 FORBIDDEN (not owner)
     *
     * @param array<string,mixed> $result created by admin
     */
    #[Depends('testPostResultAsAdmin201Created')]
    public function testPutResultAction403Forbidden(array $result): void
    {
        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$userHeaders,
            json_encode(['value' => 123])
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Test DELETE /results/{id} 403 FORBIDDEN (not owner)
     *
     * @param array<string,mixed> $result created by admin
     */
    #[Depends('testPostResultAsAdmin201Created')]
    public function testDeleteResultAction403Forbidden(array $result): void
    {
        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$userHeaders
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Test DELETE /results/{id} 204 No Content (ADMIN)
     *
     * @param array<string,mixed> $result returned by testPutResultAction209ContentReturned
     * @return int resultId
     */
    #[Depends('testPutResultAction209ContentReturned')]
    public function testDeleteResultAction204NoContent(array $result): int
    {
        self::$adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );

        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertEmpty((string) $response->getContent());

        return (int) $result['id'];
    }


    /**
     * Test GET /results/{id} 404 NOT FOUND (after DELETE)
     *
     * @param int $resultId returned by testDeleteResultAction204NoContent
     */
    #[Depends('testDeleteResultAction204NoContent')]
    public function testGetResultAction404NotFound(int $resultId): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $resultId,
            [],
            [],
            self::$adminHeaders
        );

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            self::$client->getResponse()->getStatusCode()
        );
    }


    /**
     * Test DELETE /results/{id} 404 NOT FOUND
     */
    public function testDeleteResultAction404NotFound(): void
    {
        self::$adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/999999',
            [],
            [],
            self::$adminHeaders
        );

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            self::$client->getResponse()->getStatusCode()
        );
    }

    /**
     * Test GET /results/stats 200 OK
     */
    public function testGetResultsStatsAction200Ok(): void
    {
        self::$userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/stats',
            [],
            [],
            self::$userHeaders
        );

        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            (string) $response->getContent()
        );

        self::assertJson((string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true);

        self::assertArrayHasKey('stats', $body);

        $stats = $body['stats'];

        self::assertArrayHasKey('count', $stats);
        self::assertIsInt($stats['count']);

        if ($stats['count'] > 0) {
            self::assertArrayHasKey('min', $stats);
            self::assertArrayHasKey('max', $stats);
            self::assertArrayHasKey('avg', $stats);

            self::assertIsInt($stats['min']);
            self::assertIsInt($stats['max']);
            self::assertIsFloat($stats['avg']);
        }
    }



}