# Unofficial Music Yandex Api PHP Wrapper

Install

```bash
composer require sonrac/yandex-music 
```

or add to your `composer.json` to `require` section

```json
{
  "sonrac/yandex-music": "1.0"
}
```

## Usages

* Create instance

```php
use sonrac\YandexMusic\Yandex;

$api = new Yandex('username', 'password');
```

* Create instance with proxy. Proxy getting from [gimmeProxy](https://gimmeproxy.com/api/getProxy)

```php
use sonrac\YandexMusic\Yandex;

$api = new Yandex('username', 'password', true);
```

## Api methods

* Get account status

```php
$accountStatus = $api->getAccountStatus();
```

* Get feed

```php
$feed = $api->getFeed();
```

* Get genres

```php
$genres = $api->getGenres();
```

* Search

```php
$searchResult = $api->search('text', 0);
```

* Get user play lists

```php

// For current user
$playLists = $api->getUserPlayLists();

// For some user with uid 1000
$playLists = $api->getUserPlayLists(1000);

* Get play list

```php
$playlist = $api->getPlayList(100);
```

* Get play lists

```php
$playlists = $api->getPlayLists([1003, 1010]);
```

* Get play lists

```php
$playlists = $api->getPlayLists([1003, 1010]);
```

* Remove play list

```php
$resultRemove = $api->removePlaylist(1003);
```

* Rename play list

```php
$resultRename = $api->renamePlaylist(1003, 'new playlist');
```

* Add tracks to play list

```php
$resultRename = $api->addTrackToPlaylist(1003, [1,2,3,4]);
```

* Remove tracks from play list

```php
$resultRename = $api->removeTracksFromPlaylist(1003, [1,2,3,4]);
```
