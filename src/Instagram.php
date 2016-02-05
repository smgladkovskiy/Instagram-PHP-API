<?php namespace MetzWeb\Instagram;

use MetzWeb\Instagram\Exceptions\CurlException;
use MetzWeb\Instagram\Exceptions\InstagramException;
use MetzWeb\Instagram\Exceptions\PaginationException;

/**
 * Instagram API class
 *
 * API Documentation: http://instagram.com/developer/
 * Class Documentation: https://github.com/cosenary/Instagram-PHP-API
 *
 * @author    Christian Metz
 * @since     30.10.2011
 * @copyright Christian Metz - MetzWeb Networks 2011-2014
 * @version   2.2
 * @license   BSD http://www.opensource.org/licenses/bsd-license.php
 */
class Instagram
{

    /**
     * The API base URL.
     */
    const API_URL = 'https://api.instagram.com/v1/';

    /**
     * The API OAuth URL.
     */
    const API_OAUTH_URL = 'https://api.instagram.com/oauth/authorize';

    /**
     * The OAuth token URL.
     */
    const API_OAUTH_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

    /**
     * Last API Call HTTP Status Code.
     *
     * @var int
     */
    protected $httpCode;

    /**
     * The Instagram API Key.
     *
     * @var string
     */
    private $apiKey;

    /**
     * The Instagram OAuth API secret.
     *
     * @var string
     */
    private $apiSecret;

    /**
     * The callback URL.
     *
     * @var string
     */
    private $apiCallback;

    /**
     * The user access token.
     *
     * @var string
     */
    private $accessToken;

    /**
     * Whether a signed header should be used.
     *
     * @var bool
     */
    private $signedHeader = false;

    /**
     * Available scopes.
     *
     * @var string[]
     */
    private $scopes = ['basic', 'public_content', 'follower_list', 'comments', 'relationships', 'likes'];

    /**
     * Available actions.
     *
     * @var string[]
     */
    private $actions = ['follow', 'unfollow', 'block', 'unblock', 'approve', 'deny'];

    /**
     * Rate limit.
     *
     * @var int
     */
    private $xRateLimitRemaining;

    /**
     * How many connection attempts are made before giving up?
     *
     * @var int
     */
    private $curlRetries = 5;

    /**
     * Default constructor.
     *
     * @param array|string $config Instagram configuration data
     *
     * @throws InstagramException
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            // if you want to access user data
            $this->setApiKey($config['apiKey']);
            $this->setApiSecret($config['apiSecret']);
            $this->setApiCallback($config['apiCallback']);
            if ( ! empty($config['curlRetries']) and is_int($config['curlRetries'])) {
                $this->curlRetries = $config['curlRetries'];
            }
        } elseif (is_string($config)) {
            // if you only want to access public data
            $this->setApiKey($config);
        } else {
            throw new InstagramException('Error: __construct() - Configuration data is missing.');
        }
    }

    /**
     * Generates the OAuth login URL.
     *
     * @param string[] $scopes Requesting additional permissions
     *
     * @return string Instagram OAuth login URL
     *
     * @throws InstagramException
     */
    public function getLoginUrl($scopes = ['basic'])
    {
        if (is_array($scopes) and count(array_intersect($scopes, $this->scopes)) === count($scopes)) {
            $queryData = [
                'client_id'     => $this->getApiKey(),
                'redirect_uri'  => urlencode($this->getApiCallback()),
                'scope'         => implode('+', $scopes),
                'response_type' => 'code'
            ];

            $url = self::API_OAUTH_URL . '?' . http_build_query($queryData);

            return $url;
        }

        throw new InstagramException("Error: getLoginUrl() - The parameter isn't an array or invalid scope permissions used."
        );
    }

    /**
     * API Key Getter
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * API-key Setter
     *
     * @param string $apiKey
     *
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * API Callback URL Getter.
     *
     * @return string
     */
    public function getApiCallback()
    {
        return $this->apiCallback;
    }

    /**
     * API Callback URL Setter.
     *
     * @param string $apiCallback
     *
     * @return void
     */
    public function setApiCallback($apiCallback)
    {
        $this->apiCallback = $apiCallback;
    }

    /**
     * Search for a user.
     *
     * @param string $name   Instagram username
     * @param array  $params Request params
     *
     * @return mixed
     * @throws InstagramException
     */
    public function searchUser($name, $params = [])
    {
        $params = array_merge($params, ['q' => $name]);

        return $this->makeCall('users/search', $params);
    }

    /**
     * The call operator.
     *
     * @param string $function API resource path
     * @param array  $params   Request parameters
     * @param string $method   Request type GET|POST
     *
     * @return mixed
     * @throws CurlException
     * @throws InstagramException
     */
    private function makeCall($function, $params = null, $method = 'GET')
    {
        $ApiCallQuery = [];

        // All calls needs an authenticated user
        if ( ! isset($this->accessToken)) {
            throw new InstagramException("Error: makeCall() | $function - This method requires an authenticated users access token."
            );
        }
        $authMethod = ['access_token' => $this->getAccessToken()];

        $ApiCallQuery += $authMethod;

        $paramsQuery = null;
        if (isset($params) and is_array($params)) {
            $paramsQuery = http_build_query($params);
            $ApiCallQuery += $paramsQuery;
        }

        if ($this->signedHeader) {
            $ApiCallQuery += ['sig' => $this->getHeaderSignature($function, $authMethod, $params)];
        }

        $apiCall = self::API_URL . $function . '?' . http_build_query($ApiCallQuery);

        // we want JSON
        $headerData = ['Accept: application/json'];

        $connCount = 0;
        do {
            $connCount++;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiCall);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, true);

            switch ($method) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, count($params));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsQuery);
                    break;
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    break;
            }

            $jsonData = curl_exec($ch);
        } while (curl_errno($ch) and $connCount <= $this->curlRetries);

        if (curl_errno($ch) === CURLE_OK) {
            curl_close($ch);

            // split header from JSON data
            // and assign each to a variable
            list($headerContent, $jsonData) = explode("\r\n\r\n", $jsonData, 2);

            // convert header content into an array
            $headers = $this->processHeaders($headerContent);

            // get the 'X-Ratelimit-Remaining' header value
            $this->xRateLimitRemaining =
                (isset($headers['X-Ratelimit-Remaining'])) ? trim($headers['X-Ratelimit-Remaining']) : '';

            if ( ! $jsonData) {
                throw new CurlException(curl_error($ch));
            }

            $this->httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            $stdObject = json_decode($jsonData);
            if (isset($stdObject->code) and isset($stdObject->error_type)) {
                throw new InstagramException($stdObject->error_type . ': ' . $stdObject->error_message, $stdObject->code
                );
            }

            return $stdObject;
        }

        throw new CurlException(curl_error($ch));
    }

    /**
     * Access Token Getter.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Access Token Setter.
     *
     * @param object|string $data
     *
     * @return void
     */
    public function setAccessToken($data)
    {
        $token = is_object($data) ? $data->access_token : $data;

        $this->accessToken = $token;
    }

    /**
     * Sign header by using endpoint, parameters and the API secret.
     *
     * @param string
     * @param string
     * @param array
     *
     * @return string The signature
     */
    private function getHeaderSignature($endpoint, $authMethod, $params)
    {
        if ( ! is_array($params)) {
            $params = [];
        }
        if ($authMethod) {
            list($key, $value) = $authMethod;
            $params[$key] = $value;
        }

        $baseString = '/' . $endpoint;

        ksort($params);
        foreach ($params as $key => $value) {
            $baseString .= '|' . $key . '=' . $value;
        }
        $signature = hash_hmac('sha256', $baseString, $this->apiSecret, false);

        return $signature;
    }

    /**
     * Read and process response header content.
     *
     * @param array
     *
     * @return array
     */
    private function processHeaders($headerContent)
    {
        $headers = [];

        foreach (explode("\r\n", $headerContent) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
                continue;
            }

            list($key, $value) = explode(':', $line);
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Get user info.
     *
     * @param int $id Instagram user ID
     *
     * @return mixed
     */
    public function getUser($id = 0)
    {
        return $this->makeCall('users/' . $id);
    }

    /**
     * Get user activity feed.
     *
     * @param array $params Request params
     *
     * @return mixed
     * @throws InstagramException
     */
    public function getUserFeed($params = [])
    {
        return $this->makeCall('users/self/feed', $params);
    }

    /**
     * Get user recent media.
     *
     * @param int|string $id     Instagram user ID
     * @param array      $params Request params
     *
     * @return mixed
     * @throws InstagramException
     */
    public function getUserMedia($id = 'self', $params = [])
    {
        return $this->makeCall('users/' . $id . '/media/recent', $params);
    }

    /**
     * Get the liked photos of a user.
     *
     * @param array $params Request params
     *
     * @return mixed
     * @throws InstagramException
     */
    public function getUserLikes($params = [])
    {
        return $this->makeCall('users/self/media/liked', $params);
    }

    /**
     * Get the list of users this user follows
     *
     * @param int|string $id     Instagram user ID.
     * @param array      $params Request params
     *
     * @return mixed
     * @throws InstagramException
     */
    public function getUserFollows($id = 'self', $params = [])
    {
        return $this->makeCall('users/' . $id . '/follows', $params);
    }

    /**
     * Get the list of users this user is followed by.
     *
     * @param int|string $id     Instagram user ID
     * @param array      $params Request params
     *
     * @return mixed
     * @throws InstagramException
     */
    public function getUserFollower($id = 'self', $params = [])
    {
        return $this->makeCall('users/' . $id . '/followed-by', $params);
    }

    /**
     * Get information about a relationship to another user.
     *
     * @param int $id Instagram user ID
     *
     * @return mixed
     */
    public function getUserRelationship($id)
    {
        return $this->makeCall('users/' . $id . '/relationship');
    }

    /**
     * Get the value of X-RateLimit-Remaining header field.
     *
     * @return int X-RateLimit-Remaining API calls left within 1 hour
     */
    public function getRateLimit()
    {
        return $this->xRateLimitRemaining;
    }

    /**
     * Modify the relationship between the current user and the target user.
     *
     * @param string $action Action command (follow/unfollow/block/unblock/approve/deny)
     * @param int    $user   Target user ID
     *
     * @return mixed
     *
     * @throws InstagramException
     */
    public function modifyRelationship($action, $user)
    {
        if (in_array($action, $this->actions) and isset($user)) {
            return $this->makeCall('users/' . $user . '/relationship', ['action' => $action], 'POST');
        }

        throw new InstagramException('Error: modifyRelationship() | This method requires an action command and the target user id.'
        );
    }

    /**
     * Search media by its location.
     *
     * @param float $lat           Latitude of the center search coordinate
     * @param float $lng           Longitude of the center search coordinate
     * @param int   $distance      Distance in metres (default is 1km (distance=1000), max. is 5km)
     * @param int   $min_timestamp Media taken later than this timestamp (default: 5 days ago)
     * @param int   $max_timestamp Media taken earlier than this timestamp (default: now)
     *
     * @return mixed
     */
    public function searchMedia($lat, $lng, $distance = 1000, $min_timestamp = null, $max_timestamp = null)
    {
        return $this->makeCall('media/search', compact('lat', 'lng', 'distance', 'min_timestamp', 'max_timestamp'));
    }

    /**
     * Get media by its id.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function getMedia($id)
    {
        if (is_numeric($id)) {
            return $this->makeCall('media/' . $id);
        } else {
            return $this->makeCall('media/shortcode/' . $id);
        }
    }

    /**
     * Search for tags by name.
     *
     * @param string $name Valid tag name
     *
     * @return mixed
     */
    public function searchTags($name)
    {
        return $this->makeCall('tags/search', ['q' => $name]);
    }

    /**
     * Get info about a tag
     *
     * @param string $name Valid tag name
     *
     * @return mixed
     */
    public function getTag($name)
    {
        return $this->makeCall('tags/' . $name);
    }

    /**
     * Get a recently tagged media.
     *
     * @param string $name   Valid tag name
     * @param array  $params Request parameters
     *
     * @return mixed
     */
    public function getTagMedia($name, $params = [])
    {
        return $this->makeCall('tags/' . $name . '/media/recent', $params);
    }

    /**
     * Get a list of users who have liked this media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function getMediaLikes($id)
    {
        return $this->makeCall('media/' . $id . '/likes');
    }

    /**
     * Get a list of comments for this media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function getMediaComments($id)
    {
        return $this->makeCall('media/' . $id . '/comments');
    }

    /**
     * Add a comment on a media.
     *
     * @param int    $id   Instagram media ID
     * @param string $text Comment content
     *
     * @return mixed
     */
    public function addMediaComment($id, $text)
    {
        return $this->makeCall('media/' . $id . '/comments', ['text' => $text], 'POST');
    }

    /**
     * Remove user comment on a media.
     *
     * @param int    $id        Instagram media ID
     * @param string $commentID User comment ID
     *
     * @return mixed
     */
    public function deleteMediaComment($id, $commentID)
    {
        return $this->makeCall('media/' . $id . '/comments/' . $commentID, null, 'DELETE');
    }

    /**
     * Set user like on a media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function likeMedia($id)
    {
        return $this->makeCall('media/' . $id . '/likes', null, 'POST');
    }

    /**
     * Remove user like on a media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function deleteLikedMedia($id)
    {
        return $this->makeCall('media/' . $id . '/likes', null, 'DELETE');
    }

    /**
     * Get information about a location.
     *
     * @param int $id Instagram location ID
     *
     * @return mixed
     */
    public function getLocation($id)
    {
        return $this->makeCall('locations/' . $id);
    }

    /**
     * Get recent media from a given location.
     *
     * @param int $id Instagram location ID
     *
     * @return mixed
     */
    public function getLocationMedia($id)
    {
        return $this->makeCall('locations/' . $id . '/media/recent');
    }

    /**
     * Get recent media from a given location.
     *
     * @param float $lat      Latitude of the center search coordinate
     * @param float $lng      Longitude of the center search coordinate
     * @param int   $distance Distance in meter (max. distance: 5km = 5000)
     *
     * @return mixed
     */
    public function searchLocation($lat, $lng, $distance = 1000)
    {
        return $this->makeCall('locations/search', ['lat' => $lat, 'lng' => $lng, 'distance' => $distance]);
    }

    /**
     * Pagination feature.
     *
     * @param object $obj   Instagram object returned by a method
     * @param int    $limit Limit of returned results
     *
     * @return mixed
     * @throws CurlException
     * @throws InstagramException
     * @throws PaginationException
     */
    public function pagination($obj, $limit = 0)
    {
        if (is_object($obj) and ! is_null($obj->pagination)) {
            if ( ! isset($obj->pagination->next_url)) {
                return null;
            }

            $apiCall = explode('?', $obj->pagination->next_url);

            if (count($apiCall) < 2) {
                return null;
            }

            $function = str_replace(self::API_URL, '', $apiCall[0]);

            $auth = (strpos($apiCall[1], 'access_token') !== false);

            if (isset($obj->pagination->next_max_id)) {
                return $this->makeCall($function,
                    $auth,
                    ['max_id' => $obj->pagination->next_max_id, 'count' => $limit]
                );
            } elseif (isset($obj->pagination->next_max_like_id)) {
                return $this->makeCall($function,
                    $auth,
                    ['max_like_id' => $obj->pagination->next_max_like_id, 'count' => $limit]
                );
            } elseif (isset($obj->pagination->max_tag_id)) {
                return $this->makeCall($function,
                    $auth,
                    ['max_tag_id' => $obj->pagination->max_tag_id, 'count' => $limit]
                );
            }

            return $this->makeCall($function, $auth, ['cursor' => $obj->pagination->next_cursor, 'count' => $limit]);
        }

        throw new PaginationException();
    }

    /**
     * Get the OAuth data of a user by the returned callback code.
     *
     * @param string $code  OAuth2 code variable (after a successful login)
     * @param bool   $token If it's true, only the access token will be returned
     *
     * @return mixed
     */
    public function getOAuthToken($code, $token = false)
    {
        $apiData = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->getApiKey(),
            'client_secret' => $this->getApiSecret(),
            'redirect_uri'  => $this->getApiCallback(),
            'code'          => $code
        ];

        $result = $this->makeOAuthCall($apiData);

        return ! $token ? $result : $result->access_token;
    }

    /**
     * API Secret Getter.
     *
     * @return string
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * API Secret Setter
     *
     * @param string $apiSecret
     *
     * @return void
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * The OAuth call operator.
     *
     * @param array $apiData The post API data
     *
     * @return mixed
     * @throws CurlException
     * @throws InstagramException
     */
    private function makeOAuthCall($apiData)
    {
        $apiHost = self::API_OAUTH_TOKEN_URL;

        $connCount = 0;
        do {
            $connCount++;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiHost);
            curl_setopt($ch, CURLOPT_POST, count($apiData));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            $jsonData = curl_exec($ch);
        } while (curl_errno($ch) and $connCount <= $this->curlRetries);

        if (curl_errno($ch) === CURLE_OK) {
            curl_close($ch);

            if ( ! $jsonData) {
                throw new CurlException(curl_error($ch));
            }

            $stdObject = json_decode($jsonData);
            if (isset($stdObject->code) and isset($stdObject->error_type)) {
                throw new InstagramException($stdObject->error_type . ': ' . $stdObject->error_message, $stdObject->code
                );
            }

            return $stdObject;
        }
        throw new CurlException(curl_error($ch));
    }

    /**
     * Enforce Signed Header.
     *
     * @param bool $signedHeader
     *
     * @return void
     */
    public function setSignedHeader($signedHeader)
    {
        $this->signedHeader = $signedHeader;
    }

    /**
     * Last API Call HTTP Status Code Getter.
     *
     * @return int
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }
}
