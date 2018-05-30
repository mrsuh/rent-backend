<?php

namespace AppBundle\Request;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mrsuh\Service\AuthService;

class VkPrivateRequest
{
    private $auth;
    private $client;
    private $url;
    private $version;

    /**
     * VkPrivateRequest constructor.
     * @param Client $client
     * @param string $username
     * @param string $password
     * @param string $appId
     */
    public function __construct(Client $client, string $username, string $password, string $appId)
    {
        $this->auth = new AuthService([
            'username' => $username,
            'password' => $password,
            'app_id'   => $appId,
            'scope'    => ['wall', 'photos']
        ]);

        $this->client  = $client;
        $this->url     = 'https://api.vk.com/method';
        $this->version = 5.64;

        $this->auth->auth();
    }

    /**
     * @param array $data
     * @return Response
     */
    public function photosGetWallUploadServer(array $data): Response
    {
        $data['v']            = $this->version;
        $data['access_token'] = $this->auth->getToken();

        return $this->authRequest(new Request('GET', $this->url . '/photos.getWallUploadServer'), ['query' => $data]);
    }

    /**
     * @param string $url
     * @param array  $data
     * @return Response
     */
    public function uploadPhoto(string $url, array $data): Response
    {
        return $this->client->send(new Request('POST', $url), ['multipart' => [$data]]);
    }

    /**
     * @param array $data
     * @return Response
     */
    public function photosSaveWallPhoto(array $data): Response
    {
        $data['v']            = $this->version;
        $data['access_token'] = $this->auth->getToken();

        return $this->authRequest(new Request('GET', $this->url . '/photos.saveWallPhoto'), ['query' => $data]);
    }

    /**
     * @param array $data
     * @return Response
     */
    public function wallPost(array $data): Response
    {
        $data['v']            = $this->version;
        $data['access_token'] = $this->auth->getToken();

        return $this->authRequest(new Request('POST', $this->url . '/wall.post'), ['form_params' => $data]);
    }

    /**
     * @param Request $request
     * @param array   $data
     * @param int     $try
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function authRequest(Request $request, array $data, $try = 0)
    {
        try {

            $response = $this->client->send($request, $data);

            $content = $response->getBody()->getContents();
            $response->getBody()->rewind();

            $data = json_decode($content, true);

            if (!is_array($data)) {
                return $response;
            }

            if (array_key_exists('error', $data) && array_key_exists('error_code', $data['error'])) {
                if (5 === (int)$data['error']['error_code']) {
                    throw new \Exception('Auth exception');
                }
            }

            return $response;

        } catch (\Exception $e) {

            if ($try > 1) {
                throw $e;
            }

            $this->auth->auth();

            $try++;

            return $this->authRequest($request, $data, $try);
        }
    }

    public function groupsSearch(int $city, string $query): Response
    {
        $form_params = [
            'v'            => $this->version,
            'access_token' => $this->auth->getToken(),
            'q'            => $query,
            'city_id'      => $city,
            'sort'         => 2,
            'count'        => 500
        ];

        return $this->authRequest(new Request('POST', $this->url . '/groups.search'), ['form_params' => $form_params]);
    }

    public function databaseGetCountries(): Response
    {
        $form_params = [
            'v'            => $this->version,
            'access_token' => $this->auth->getToken(),
            'count'        => 5,
            'code'         => 'RU',
            'need_all'     => 1
        ];

        return $this->authRequest(new Request('POST', $this->url . '/database.getCountries'), ['form_params' => $form_params]);
    }

    public function boardGetTopics(int $group_id): Response
    {
        $form_params = [
            'v'            => $this->version,
            'access_token' => $this->auth->getToken(),
            'group_id'     => $group_id
        ];

        return $this->authRequest(new Request('POST', $this->url . '/board.getTopics'), ['form_params' => $form_params]);
    }

    public function databaseGetCities(string $query): Response
    {
        $form_params = [
            'v'            => $this->version,
            'access_token' => $this->auth->getToken(),
            'group_id'     => 5,
            'country_id'   => 1,
            'q'            => $query
        ];

        return $this->authRequest(new Request('POST', $this->url . '/database.getCities'), ['form_params' => $form_params]);
    }

    public function groupsGetById(array $group_ids): Response
    {
        $form_params = [
            'v'            => $this->version,
            'access_token' => $this->auth->getToken(),
            'group_ids'    => implode(',', $group_ids)
        ];

        return $this->authRequest(new Request('POST', $this->url . '/groups.getById'), ['form_params' => $form_params]);
    }
}