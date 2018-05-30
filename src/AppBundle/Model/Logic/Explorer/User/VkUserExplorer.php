<?php

namespace AppBundle\Model\Logic\Explorer\User;

use AppBundle\Exception\ExploreException;
use AppBundle\Request\VkPublicRequest;

class VkUserExplorer implements UserExplorerInterface
{
    private $request;

    /**
     * @var User[]
     */
    private $cache;

    /**
     * VkUserExplorer constructor.
     * @param VkPublicRequest $request
     */
    public function __construct(VkPublicRequest $request)
    {
        $this->request = $request;
        $this->cache   = [];
    }

    /**
     * @param int $id
     * @return User
     * @throws ExploreException
     */
    public function explore(int $id): User
    {
        $key = $id;

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->exploreUser($id);
        }

        return $this->cache[$key];
    }

    /**
     * @param string $id
     * @return User
     * @throws ExploreException
     */
    private function exploreUser(string $id)
    {
        $response = $this->request->getUserInfo($id);

        $contents = $response->getBody()->getContents();
        $info = json_decode($contents, true);

        if (!array_key_exists('response', $info)) {
            throw new ExploreException('Has not key "response" in response' . $contents);
        }

        $data = [];
        foreach ($info['response'] as $i) {
            switch (true) {
                case array_key_exists('id', $i):
                    $user_id = (string)$i['id'];
                    break;
                case array_key_exists('uid', $i):
                    $user_id = (string)$i['uid'];
                    break;
                default:
                    $user_id = null;
            }

            if ((string)$user_id === (string)$id) {
                $data = $i;
                break;
            }
        }

        if (empty($data)) {

            return new User();
        }

        foreach (['first_name'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new ExploreException(sprintf('Has not key "%s" in response', $key));
            }
        }

        $user =
            (new User())
                ->setName($data['first_name'])
                ->setBlacklisted(false);

        if (array_key_exists('blacklisted', $data) && $data['blacklisted']) {
            $user->setBlacklisted(true);
        }

        return $user;
    }
}