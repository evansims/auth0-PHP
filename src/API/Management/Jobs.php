<?php

declare(strict_types=1);

namespace Auth0\SDK\API\Management;

use Auth0\SDK\Utility\Request\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Jobs.
 * Handles requests to the Jobs endpoint of the v2 Management API.
 *
 * @link https://auth0.com/docs/api/management/v2#!/Jobs
 */
final class Jobs extends ManagementEndpoint
{
    /**
     * Import users from a formatted file into a connection via a long-running job.
     * Required scopes:
     * - `create:users`
     * - `read:users`
     *
     * @param string              $filePath     Path to formatted file to import.
     * @param string              $connectionId Id of the Connection to use.
     * @param array               $parameters   Additional query parameters to pass with the API request. See @link for supported options.
     * @param RequestOptions|null $options      Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When the API request fails due to a network error.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Jobs/post_users_imports
     */
    public function createImportUsers(
        string $filePath,
        string $connectionId,
        array $parameters = [],
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateString($filePath, 'filePath');
        $this->validateString($connectionId, 'connectionId');

        $request = $this->getHttpClient()->method('post')
            ->addPath('jobs', 'users-imports')
            ->addFile('users', $filePath)
            ->withFormParam('connection_id', $connectionId);

        foreach ($parameters as $key => $value) {
            $request->withFormParam($key, $value);
        }

        return $request->withOptions($options)
            ->call();
    }

    /**
     * Export all users to a file via a long-running job.
     * Required scope: `read:users`
     *
     * @param array               $body    Additional body content to pass with the API request. See @link for supported options.
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When the API request fails due to a network error.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Jobs/post_users_exports
     */
    public function createExportUsers(
        array $body = [],
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateArray($body, 'body');

        return $this->getHttpClient()->method('post')
            ->addPath('jobs', 'users-exports')
            ->withBody((object) $body)
            ->withOptions($options)
            ->call();
    }

    /**
     * Create a verification email job.
     * Required scope: `update:users`
     *
     * @param string              $userId  User ID of the user to send the verification email to.
     * @param array               $body    Optional. Additional body content to pass with the API request. See @link for supported options.
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When the API request fails due to a network error.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Jobs/post_verification_email
     */
    public function createSendVerificationEmail(
        string $userId,
        array $body = [],
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateString($userId, 'userId');

        $payload = [
            'user_id' => $userId,
        ] + $body;

        return $this->getHttpClient()->method('post')
            ->addPath('jobs', 'verification-email')
            ->withBody((object) $payload)
            ->withOptions($options)
            ->call();
    }

    /**
     * Retrieves a job. Useful to check its status.
     * Required scopes:
     * - `create:users`
     * - `read:users`
     *
     * @param string              $id      Job (by it's ID) to query.
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When the API request fails due to a network error.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Jobs/get_jobs_by_id
     */
    public function get(
        string $id,
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateString($id, 'id');

        return $this->getHttpClient()->method('get')
            ->addPath('jobs', $id)
            ->withOptions($options)
            ->call();
    }

    /**
     * Retrieve error details of a failed job.
     * Required scopes:
     * - `create:users`
     * - `read:users`
     *
     * @param string              $id      Job (by it's ID) to query.
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When the API request fails due to a network error.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Jobs/get_errors
     */
    public function getErrors(
        string $id,
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateString($id, 'id');

        return $this->getHttpClient()->method('get')
            ->addPath('jobs', $id, 'errors')
            ->withOptions($options)
            ->call();
    }
}
