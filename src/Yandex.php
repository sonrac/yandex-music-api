<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\YandexMusic;

use Guzzle\Http\Client;
use Guzzle\Plugin\Cookie\Cookie;
use sonrac\YandexMusic\Helpers\IConfig;


/**
 * Class Yandex
 * Main wrapper for api
 *
 * @package sonrac\YandexMusic
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class Yandex
{
    /**
     * @var IConfig
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public $config;

    /**
     * @var Client
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public $client;

    /**
     * Save cookies
     *
     * @var Cookie
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected $_cookie;

    /**
     * Current proxy
     *
     * @var array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected $_proxy;

    /**
     * Cache path for save token in storage
     *
     * @var null|string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected $_cachePath = null;

    /**
     * Yandex music api constructor.
     *
     * @param string $username
     * @param string $password
     * @param bool   $useProxy
     * @param string $cachePath
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function __construct($username, $password, $useProxy = false, $cachePath = __DIR__ . '/cache-data')
    {
        $this->config = require __DIR__ . "/config.php";

        if ($useProxy) {
            $client = new Client('https://gimmeproxy.com', [
                'timeout' => 1,
            ]);
            do {
                try {
                    $proxy = $client->get('api/getProxy')->send()->json();
                    $client->get('', null, [
                        'proxy'   => $proxy['curl'],
                        'timeout' => 1,
                    ])->send()->getBody();
                    $this->_proxy = $proxy['curl'];
                    break;
                } catch (\Exception $exception) {
                }
            } while (true);
        }

        $this->client = new Client('https://api.music.yandex.net', [
            'timeout' => 60,
        ]);

        $this->_cachePath = $cachePath;

        $this->_cookie = new Cookie();

        $this->config->user->USERNAME = $username;
        $this->config->user->PASSWORD = $password;

        $this->restoreTokenFromCache();

        if (!$this->config->user->TOKEN) {
            $this->getToken();
        }
    }

    /**
     * Restore token for current user from cache
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected function restoreTokenFromCache()
    {
        if (file_exists($file = $this->_cachePath . "/{$this->config->user->USERNAME}.json")) {

            $createTime = filemtime($file);

            $data = json_decode(file_get_contents($file), true);

            if ((time() - $createTime) > $data['token_expire']) {
                return;
            }

            $this->config->user->TOKEN = $data['token'];
            $this->config->user->TOKEN_EXPIRE = $data['token_expire'];
        }
    }

    /**
     * Prepare request
     *
     * @param string $url    Url
     * @param array  $data   Request data
     * @param string $method Request method (delete|put|get|post|patch)
     *
     * @return \Guzzle\Http\Message\RequestInterface
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected function _prepareRequest(string $url, array $data = [], string $method = 'get')
    {
        $_data = $method == 'get' ? null : $data;

        $options = [
            $url,
            $this->getAuthHeaders(),
        ];

        $requestOptions = [];

        if ($this->_proxy) {
            $requestOptions['proxy'] = $this->_proxy;
        }

        if ($_data) {
            $options[] = $_data;
        }

        if ($method === 'get') {
            $pOptions = [];

            if (count($data)) {
                $pOptions['query'] = $data;
            }

            $requestOptions = array_merge($pOptions, $requestOptions);
        }

        $options[] = $requestOptions;

        return call_user_func_array([$this->client, $method], $options);
    }

    /**
     * Get auth token for user
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected function getToken()
    {
        $options = [
            'query' => [
                'device_id'    => $this->config->fake_device->DEVICE_ID,
                'device_uuid'  => $this->config->fake_device->UUID,
                'package_name' => $this->config->fake_device->PACKAGE_NAME,
            ],
        ];

        if ($this->_proxy) {
            $options['proxy'] = $this->_proxy;
        }

        $client = new Client('https://oauth.mobile.yandex.net', []);

        $resp = $client->post('1/token?', null, [
            'grant_type'    => 'password',
            'username'      => $this->config->user->USERNAME,
            'password'      => $this->config->user->PASSWORD,
            'client_id'     => $this->config->oauth_code->CLIENT_ID,
            'client_secret' => $this->config->oauth_code->CLIENT_SECRET,
        ], $options)->send()->json();

        $this->config->user->UID = $resp['uid'];

        $resp = $client->post('1/token', null, [
            'grant_type'    => 'x-token',
            'access_token'  => $resp['access_token'],
            'client_id'     => $this->config->oauth_token->CLIENT_ID,
            'client_secret' => $this->config->oauth_token->CLIENT_SECRET,
        ], $options)->send()->json();

        $this->config->user->TOKEN = $resp['access_token'];
        $this->config->user->TOKEN_EXPIRE = $resp['expires_in'];

        file_put_contents($this->_cachePath . "{$this->config->user->USERNAME}.json", json_encode([
            'token'        => $this->config->user->TOKEN,
            'token_expire' => $this->config->user->TOKEN_EXPIRE,
        ]));
    }

    /**
     * Get auth headers
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization'   => "OAuth {$this->config->user->TOKEN}",
            'Accept Language' => 'en-US,en;q=0.8',
            'Accept Encoding' => 'gzip, deflate, sdch, br',
            'Accept'          => '*/*',
            'Postman Token'   => '0602916c-c9be-3364-8938-6b4f5426539e',
            'User Agent'      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
            'Cache Control'   => 'no-cache',
            'Connection'      => 'keep-alive',
        ];
    }

    /**
     * GET: /account/status
     * Get account status for current user
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getAccountStatus(): array
    {
        return $this->_prepareRequest('account/status')->send()->json();
    }

    /**
     * GET: /feed
     * Get the user's feed
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getFeed(): array
    {
        return $this->_prepareRequest('feed')->send()->json();
    }

    /**
     * GET: /genres
     * Get a list of music genres
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getGenres(): array
    {
        return $this->_prepareRequest('genres')->send()->json();
    }

    /**
     * GET: /search
     * Search artists, tracks, albums.
     *
     * @param string $text The search query
     * @param int    $page Page number
     * @param string $type One from (artist|album|track|all)
     * @param bool   $nococrrect
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function search($text, $page = 0, $type = 'all', $nococrrect = false): array
    {
        return $this->_prepareRequest('search', [
            'type'       => $type,
            'text'       => $text,
            'page'       => $page,
            'nococrrect' => $nococrrect,
        ])->send()->json();
    }

    /**
     * GET: /users/[user_id]/playlists/list
     * Get a user's playlists.
     *
     * @param string $userID The user ID, if null then equal to current user id
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getUserPlayLists($userID = null): array
    {
        return $this->getPlayList('list', $userID);
    }

    /**
     * GET: /users/[user_id]/playlists/[playlist_kind]
     * Get a playlist without tracks
     *
     * @param string      $playListKind The playlist ID
     * @param string|null $userID       The user ID, if null then equal to current user id
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getPlayList($playListKind, $userID = null): array
    {
        return $this->_prepareRequest('/users/' . ($userID ?? $this->config->user->UID) . "/playlists/{$playListKind}")->send()->json();
    }

    /**
     * GET: /users/[user_id]/playlists
     * Get an array of playlists with tracks
     *
     * @param array       $playlists The playlists IDs. Example: [1,2,3]
     * @param bool        $mixed
     * @param bool        $richTracks
     * @param null|string $userID    The user ID, if null then equal to current user id
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getPlayLists(array $playlists, $mixed = false, $richTracks = false, $userID = null): array
    {
        return $this->_prepareRequest('/users/' . ($userID ?? $this->config->user->UID) . "/playlists", [
            'kinds'       => $playlists,
            'mixed'       => $mixed,
            'rich-tracks' => $richTracks,
        ])->send()->json();
    }

    /**
     * POST: /users/[user_id]/playlists/create
     * Create a new playlist
     *
     * @param string $name       The name of the playlist
     * @param string $visibility Visibility level. One of (public|private)
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function createPlaylist($name, $visibility = 'private'): array
    {
        return $this->_prepareRequest('/users/' . ($userID ?? $this->config->user->UID) . "/playlists/create", [
            'title'      => $name,
            'visibility' => $visibility,
        ])->send()->json();
    }

    /**
     * POST: /users/[user_id]/playlists/[playlist_kind]/delete
     * Remove a playlist
     *
     * @param string $playlistKind
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function removePlaylist($playlistKind): array
    {
        return $this->_prepareRequest('/users/' . ($userID ?? $this->config->user->UID) . "/playlists/{$playlistKind}/delete")->send()->json();
    }

    public function renamePlaylist($playlistKind, $name): array
    {
        return $this->_prepareRequest('/users/' . ($userID ?? $this->config->user->UID) . "/playlists/{$playlistKind}/name", [
            'value' => $name,
        ])->send()->json();
    }

    /**
     * POST: /users/[user_id]/playlists/[playlist_kind]/change-relative
     * Add tracks to the playlist
     *
     * @param string $playlistKind The playlist's ID
     * @param array  $tracks       An array of objects containing a track info:
     *                             track id and album id for the track.
     *                             Example: [{id:'20599729', albumId:'2347459'}]
     * @param string $revision     Operation id for that request
     * @param int    $at
     * @param string $op           Operation
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function addTrackToPlaylist($playlistKind, $tracks, $revision, $at = 0, $op = 'insert'): array
    {
        return $this->_prepareRequest('/users/' . ($userID ?? $this->config->user->UID) . "/playlists/{$playlistKind}/change-relative", [
            'diff'     => json_encode([
                'op'     => $op,
                'at'     => $at,
                'tracks' => $tracks,
            ]),
            'revision' => $revision,
        ])->send()->json();
    }

    /**
     * POST: /users/[user_id]/playlists/[playlist_kind]/change-relative
     * Remove tracks from the playlist
     *
     * @param string $playlistKind Th   e playlist's ID
     * @param array  $tracks       An array of objects containing a track info:
     *                             track id and album id for the track.
     *                             Example: [{id:'20599729', albumId:'2347459'}]
     * @param string $revision     Operation id for that request
     * @param int    $at
     * @param string $op           Operation
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function removeTracksFromPlaylist($playlistKind, $tracks, $revision, $at = 0): array
    {
        return $this->addTrackToPlaylist($playlistKind, $tracks, $revision, $at, 'delete');
    }
}