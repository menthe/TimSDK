<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 6/14/2018
 * Time: 8:08 PM
 */

namespace TimSDK\Core;

use TimSDK\Support\Str;
use TimSDK\Support\Log;
use TimSDK\Support\Arr;
use TimSDK\Support\Collection;
use TimSDK\Core\Exceptions\HttpException;
use TimSDK\Core\Exceptions\MissingArgumentsException;

class IMCloud extends BaseIMCloud
{
    /**
     * @var Collection
     */
    protected $query;

    /**
     * @var bool
     */
    protected $needRefresh = false;

    /**
     * Set on refresh swith
     */
    public function needRefresh()
    {
        $this->needRefresh = true;
    }

    /**
     * @return bool
     */
    public function isNeedRefresh()
    {
        return (bool) $this->needRefresh;
    }

    /**
     * Init
     *
     * @throws MissingArgumentsException
     * @throws \TimSDK\Core\Exceptions\UserSigException
     */
    public function initialize()
    {
        $this->getRefreshedQueryStringCollection(true);
    }

    /**
     * Request api
     *
     * @param       $uri
     * @param array $data
     * @param array $options
     * @return \TimSDK\Foundation\ResponseBag
     * @throws \TimSDK\Core\Exceptions\JsonParseException
     * @throws \TimSDK\Core\Exceptions\UserSigException
     * @throws \TimSDK\Core\Exceptions\HttpException
     * @throws \TimSDK\Core\Exceptions\MissingArgumentsException
     */
    public function handle($uri, $data = [], $options = [])
    {
        if (empty($data)) {
            $data = '{}';
        }

        $response = $this->httpPostJson($uri, $data, array_merge($options, [
            'query' => $this->getRefreshedQueryStringArray()
        ]));

        $this->checkAndThrow($response->getContents());

        return $response;
    }

    /**
     * Generate sig
     *
     * @param $identifier
     * @return string
     * @throws \TimSDK\Core\Exceptions\UserSigException
     */
    public function generateSig($identifier)
    {
        return $this->app['TLSSig']->genSig($identifier);
    }

    /**
     * Refresh query string
     *
     * @param bool $force
     * @return Collection
     * @throws Exceptions\UserSigException
     * @throws MissingArgumentsException
     */
    public function getRefreshedQueryStringCollection($force = false)
    {
        if ($this->needRefresh || $force) {
            $this->needRefresh = false;
            $this->query = $this->getQueryStringCollection();
            $this->query->setAll($this->getLatestQueryStringArray());
        }

        return $this->query;
    }

    /**
     * Get the refreshed query string
     *
     * @return array
     * @throws Exceptions\UserSigException
     * @throws MissingArgumentsException
     */
    public function getRefreshedQueryStringArray()
    {
        return $this->getRefreshedQueryStringCollection()->toArray();
    }

    /**
     * Query Getter
     *
     * @return Collection
     */
    public function getQueryStringCollection()
    {
        if (!$this->query instanceof Collection) {
            $this->query = new Collection();
        }

        return $this->query;
    }

    /**
     * Get the latest query string array
     *
     * @return array
     * @throws Exceptions\UserSigException
     * @throws MissingArgumentsException
     */
    public function getLatestQueryStringArray()
    {
        $data = Arr::only($this->app['config']->all(), [
            'sdkappid',
            'identifier',
            'prikey',
            'pubkey',
            'random',
            'contenttype',
        ]);

        if (!isset($data['random'])) {
            $data['random'] = time();
        }

        if (!isset($data['contenttype'])) {
            $data['contenttype'] = 'json';
        }

        foreach (['sdkappid', 'identifier', 'prikey', 'pubkey'] as $item) {
            if (!isset($data[$item])) {
                Log::debug('IMCloud Query: ', $data);
                throw new MissingArgumentsException('Missing ' . $item);
            }
        }

        $data['usersig'] = $this->generateSig($data['identifier']);

        return $data;
    }

    /**
     * Get a full url
     *
     * @param $uri
     * @return string
     */
    protected function getFullApiUrl($uri)
    {
        return Str::startsWith($uri, ['http', 'https']) ? $uri : API::BASE_URL . $uri;
    }

    /**
     * Check the array data errors, and Throw exception when the contents contains error.
     *
     * @param array|Collection $contents
     *
     * @throws HttpException
     */
    protected function checkAndThrow($contents)
    {
        if ($contents instanceof Collection) {
            $contents = $contents->toArray();
        }

        if (isset($contents['ErrorCode']) && 0 !== $contents['ErrorCode']) {
            if (empty($contents['ErrorInfo'])) {
                $contents['ErrorInfo'] = 'Unknown';
            }
            throw new HttpException($contents['ErrorInfo'], $contents['ErrorCode']);
        }
    }
}
