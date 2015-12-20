<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use App\Models\User;
use App\Services\Lastfm;
use App\Http\Controllers\API\LastfmController;
use Illuminate\Routing\Redirector;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;;
use Mockery as m;

class LastfmTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    public function testGetArtistInfo()
    {
        $client = m::mock(Client::class, [
            'get' => new Response(200, [], file_get_contents(dirname(__FILE__).'/blobs/lastfm/artist.xml')),
        ]);

        $api = new Lastfm(null, null, $client);

        $this->assertEquals([
            'url' => 'http://www.last.fm/music/Kamelot',
            'image' => 'http://foo.bar/extralarge.jpg',
            'bio' => [
                'summary' => 'Quisque ut nisi.',
                'full' => 'Quisque ut nisi. Vestibulum ullamcorper mauris at ligula.',
            ],
        ], $api->getArtistInfo('foo'));

        // Is it cached?
        $this->assertNotNull(Cache::get(md5('lastfm_artist_foo')));
    }

    public function testGetArtistInfoFailed()
    {
        $client = m::mock(Client::class, [
            'get' => new Response(400, [], file_get_contents(dirname(__FILE__).'/blobs/lastfm/artist-notfound.xml')),
        ]);

        $api = new Lastfm(null, null, $client);

        $this->assertFalse($api->getArtistInfo('foo'));
    }

    public function testGetAlbumInfo()
    {
        $client = m::mock(Client::class, [
            'get' => new Response(200, [], file_get_contents(dirname(__FILE__).'/blobs/lastfm/album.xml')),
        ]);

        $api = new Lastfm(null, null, $client);

        $this->assertEquals([
            'url' => 'http://www.last.fm/music/Kamelot/Epica',
            'image' => 'http://foo.bar/extralarge.jpg',
            'tracks' => [
                [
                    'title' => 'Track 1',
                    'url' => 'http://foo/track1',
                    'length' => 100,
                ],
                [
                    'title' => 'Track 2',
                    'url' => 'http://foo/track2',
                    'length' => 150,
                ],
            ],
            'wiki' => [
                'summary' => 'Quisque ut nisi.',
                'full' => 'Quisque ut nisi. Vestibulum ullamcorper mauris at ligula.',
            ],
        ], $api->getAlbumInfo('foo', 'bar'));

        // Is it cached?
        $this->assertNotNull(Cache::get(md5('lastfm_album_foo_bar')));
    }

    public function testGetAlbumInfoFailed()
    {
        $client = m::mock(Client::class, [
            'get' => new Response(400, [], file_get_contents(dirname(__FILE__).'/blobs/lastfm/album-notfound.xml')),
        ]);

        $api = new Lastfm(null, null, $client);

        $this->assertFalse($api->getAlbumInfo('foo', 'bar'));
    }

    public function testBuildAuthCallParams()
    {
        $api = new Lastfm('key', 'secret');
        $params = [
            'qux' => '安',
            'bar' => 'baz',
        ];

        $this->assertEquals([
            'api_key' => 'key',
            'bar' => 'baz',
            'qux' => '安',
            'api_sig' => '7f21233b54edea994aa0f23cf55f18a2',
        ], $api->buildAuthCallParams($params));

        $this->assertEquals('api_key=key&bar=baz&qux=安&api_sig=7f21233b54edea994aa0f23cf55f18a2', 
            $api->buildAuthCallParams($params, true));
    }

    public function testGetSessionKey()
    {
        $client = m::mock(Client::class, [
            'get' => new Response(200, [], file_get_contents(dirname(__FILE__).'/blobs/lastfm/session-key.xml')),
        ]);

        $api = new Lastfm(null, null, $client);

        $this->assertEquals('foo', $api->getSessionKey('bar'));
    }

    public function testControllerConnect()
    {
        $redirector = m::mock(Redirector::class);
        $redirector->shouldReceive('to')->once();

        $guard = m::mock(Guard::class, ['user' => factory(User::class)->create()]);

        (new LastfmController($guard))->connect($redirector, new Lastfm());
    }

    public function testControllerCallback()
    {
        $request = m::mock(Request::class, ['input' => 'token']);
        $lastfm = m::mock(Lastfm::class, ['getSessionKey' => 'bar']);

        $user = factory(User::class)->create();
        $guard = m::mock(Guard::class, ['user' => $user]);

        (new LastfmController($guard))->callback($request, $lastfm);

        $this->assertEquals('bar', $user->getPreference('lastfm_session_key'));
    }

    public function testControllerDisconnect()
    {
        $user = factory(User::class)->create(['preferences' => ['lastfm_session_key' => 'bar']]);
        $this->actingAs($user)->delete('api/lastfm/disconnect');
        $this->assertNull($user->getPreference('lastfm_session_key'));
    }
}
