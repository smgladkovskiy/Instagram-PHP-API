<?php namespace MetzWeb\Instagram;

use GuzzleHttp\Client;
use MetzWeb\Instagram\Exceptions\InstagramException;
use MetzWeb\Instagram\Exceptions\PaginationException;

/**
 * Instagram API class
 *
 * API Documentation: http://instagram.com/developer/
 * Class Documentation: https://github.com/cosenary/Instagram-PHP-API
 *
 * @author    Christian Metz
 * @author    Sergey Gladkovskiy <smgladkovskiy@gmail.com>
 * @since     30.10.2011
 * @copyright Christian Metz - MetzWeb Networks 2011-2014
 * @version   3.0-alfa
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
    private $httpCode;

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
     * Headers array from response
     *
     * @var array
     */
    private $headers = [];

    /**
     * Default constructor.
     *
     * @param array $config Instagram configuration data
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
        } else {
            throw new InstagramException('Error: __construct() - Configuration data is missing.');
        }
    }

    /**
     * Generates the OAuth login URL.
     *
     * @param array $scopes Requesting additional permissions
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

            $url = self::API_OAUTH_URL . '?' . $this->buildQueryParams($queryData);

            return $url;
        }

        throw new InstagramException("Error: getLoginUrl() - The parameter isn't an array or invalid scope permissions used.");
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

        return $this->get('users/search', $params);
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
     * Get user info.
     *
     * @param int $id Instagram user ID
     *
     * @return mixed
     */
    public function getUser($id = 0)
    {
        return $this->get('users/' . $id);
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
        return $this->get('users/self/feed', $params);
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
        return $this->get('users/' . $id . '/media/recent', $params);
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
        return $this->get('users/self/media/liked', $params);
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
        return $this->get('users/' . $id . '/follows', $params);
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
        return $this->get('users/' . $id . '/followed-by', $params);
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
        return $this->get('users/' . $id . '/relationship');
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
            return $this->post('users/' . $user . '/relationship', ['action' => $action]);
        }

        throw new InstagramException('Error: modifyRelationship() | This method requires an action command and the target user id.');
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
        return $this->get('media/search', compact('lat', 'lng', 'distance', 'min_timestamp', 'max_timestamp'));
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
            return $this->get('media/' . $id);
        } else {
            return $this->get('media/shortcode/' . $id);
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
        return $this->get('tags/search', ['q' => $name]);
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
        return $this->get('tags/' . $name);
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
        return $this->get('tags/' . $name . '/media/recent', $params);
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
        return $this->get('media/' . $id . '/likes');
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
        return $this->get('media/' . $id . '/comments');
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
        return $this->post('media/' . $id . '/comments', ['text' => $text]);
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
        return $this->delete('media/' . $id . '/comments/' . $commentID);
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
        return $this->post('media/' . $id . '/likes', null);
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
        return $this->delete('media/' . $id . '/likes');
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
        return $this->get('locations/' . $id);
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
        return $this->get('locations/' . $id . '/media/recent');
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
        return $this->get('locations/search', ['lat' => $lat, 'lng' => $lng, 'distance' => $distance]);
    }

    /**
     * Pagination feature.
     *
     * @param object $obj   Instagram object returned by a method
     * @param int    $limit Limit of returned results
     *
     * @return mixed
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

            if (isset($obj->pagination->next_max_id)) {
                return $this->get($function, ['max_id' => $obj->pagination->next_max_id, 'count' => $limit]);
            } elseif (isset($obj->pagination->next_max_like_id)) {
                return $this->get($function, ['max_like_id' => $obj->pagination->next_max_like_id, 'count' => $limit]);
            } elseif (isset($obj->pagination->max_tag_id)) {
                return $this->get($function, ['max_tag_id' => $obj->pagination->max_tag_id, 'count' => $limit]);
            }

            return $this->get($function, ['cursor' => $obj->pagination->next_cursor, 'count' => $limit]);
        }

        throw new PaginationException;
    }

    /**
     * Get the OAuth data of a user by the returned callback code.
     *
     * @param string $code OAuth2 code variable (after a successful login)
     *
     * @return mixed
     */
    public function getOAuthToken($code)
    {
        $apiData = [
            'client_id'     => $this->getApiKey(),
            'client_secret' => $this->getApiSecret(),
            'redirect_uri'  => $this->getApiCallback(),
            'grant_type'    => 'authorization_code',
            'code'          => $code
        ];

        $response = $this->sendRequest(self::API_OAUTH_TOKEN_URL, 'POST', $apiData);

        return $response->access_token;
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

    private function get($function, $params = [])
    {
        return $this->makeCall($function, $params);
    }

    private function post($function, $params = [])
    {
        return $this->makeCall($function, $params, 'POST');
    }

    private function delete($function, $params = [])
    {
        return $this->makeCall($function, $params, 'DELETE');
    }

    /**
     * The call operator.
     *
     * @param string $function API resource path
     * @param array  $params   Request parameters
     * @param string $method   Request type GET|POST
     *
     * @return mixed
     * @throws InstagramException
     */
    private function makeCall($function, $params = null, $method = 'GET')
    {
        // All calls needs an authenticated user
        if ( ! isset($this->accessToken)) {
            throw new InstagramException("Error: makeCall() | $function - This method requires an authenticated users access token.");
        }

        $apiCallQuery = ['access_token' => $this->getAccessToken()];

        if (isset($params) and is_array($params)) {
            $apiCallQuery += $params;
        }

        if ($this->signedHeader) {
            $apiCallQuery += ['sig' => $this->getHeaderSignature($function, $params)];
        }

        $response = $this->sendRequest(self::API_URL . $function, $method, $apiCallQuery);

        // get the 'X-Ratelimit-Remaining' header value
        $this->xRateLimitRemaining =
            (isset($this->headers['X-Ratelimit-Remaining'])) ? trim($this->headers['X-Ratelimit-Remaining'][0]) : '';

        return $response;
    }

    /**
     * Sign header by using endpoint, parameters and the API secret.
     *
     * @param string $endpoint Request endpoint
     * @param array  $params   Request params
     *
     * @return string The signature
     */
    private function getHeaderSignature($endpoint, $params)
    {
        if ( ! is_array($params)) {
            $params = [];
        }

        if ($this->getAccessToken()) {
            $params['access_token'] = $this->getAccessToken();
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
     * Prepare query params for request to Instagram api (it doesn't like encoded characters)
     *
     * @param $queryData
     *
     * @return string
     */
    private function buildQueryParams($queryData)
    {
        $query = [];
        foreach ($queryData as $key => $value) {
            if (is_array($value)) {
                $query[] = $this->buildQueryParams($value);
            } else {
                $query[] = "$key=$value";
            }
        }

        return implode('&', $query);
    }

    /**
     * Make Guzzle request
     *
     * @param $host
     * @param $method
     * @param $requestData
     *
     * @return mixed
     * @throws InstagramException
     */
    private function sendRequest($host, $method, $requestData)
    {
        $guzzle_config = [
            'debug'      => false,
            'exceptions' => false
        ];

        if ($method == 'POST') {
            $requestData = ['form_params' => $requestData];
        } else {
            $requestData = ['query' => $requestData];
        }

        $client = new Client($guzzle_config);

        $response       = $client->request($method, $host, $requestData);
        $this->httpCode = $response->getStatusCode();
        $this->headers  = $response->getHeaders();
        $responseData   = json_decode($response->getBody()->getContents());

        if (isset($response->error_type)) {
            throw new InstagramException($response->error_type . ': ' . $response->error_message, $response->code);
        }

        return $responseData;
    }
}