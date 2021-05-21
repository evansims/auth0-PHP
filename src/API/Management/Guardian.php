<?php

declare(strict_types=1);

namespace Auth0\SDK\API\Management;

use Auth0\SDK\Helpers\Requests\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Guardian.
 * Handles requests to the Guardian endpoint of the v2 Management API.
 *
 * @link https://auth0.com/docs/api/management/v2#!/Guardian
 */
class Guardian extends GenericResource
{
    /**
     * Retrieve all multi-factor authentication configurations.
     * Required scope: `read:guardian_factors`
     *
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When API request fails. Reason for failure provided in exception message.
     *
     * @see https://auth0.com/docs/api/management/v2#!/Guardian/get_factors
     */
    public function getFactors(
        ?RequestOptions $options = null
    ): ResponseInterface {
        return $this->apiClient->method('get')
            ->addPath('guardian', 'factors')
            ->withOptions($options)
            ->call();
    }

    /**
     * Retrieve an enrollment (including its status and type).
     * Required scope: `read:guardian_enrollments`
     *
     * @param string              $id      Enrollment (by it's ID) to query.
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When API request fails. Reason for failure provided in exception message.
     *
     * @see https://auth0.com/docs/api/management/v2#!/Guardian/get_enrollments_by_id
     */
    public function getEnrollment(
        string $id,
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateString($id, 'id');

        return $this->apiClient->method('get')
            ->addPath('guardian', 'enrollments', $id)
            ->withOptions($options)
            ->call();
    }

    /**
     * Delete an enrollment to allow the user to enroll with multi-factor authentication again.
     * Required scope: `delete:guardian_enrollments`
     *
     * @param string              $id      Enrollment (by it's ID) to be deleted.
     * @param RequestOptions|null $options Optional. Additional request options to use, such as a field filtering or pagination. (Not all endpoints support these. See @link for supported options.)
     *
     * @throws \Auth0\SDK\Exception\NetworkException When API request fails. Reason for failure provided in exception message.
     *
     * @see https://auth0.com/docs/api/management/v2#!/Guardian/delete_enrollments_by_id
     */
    public function deleteEnrollment(
        string $id,
        ?RequestOptions $options = null
    ): ResponseInterface {
        $this->validateString($id, 'id');

        return $this->apiClient->method('delete')
            ->addPath('guardian', 'enrollments', $id)
            ->withOptions($options)
            ->call();
    }
}
