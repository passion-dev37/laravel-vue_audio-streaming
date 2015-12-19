<?php

namespace App\Http\Controllers\API;

use App\Http\Streamers\PHPStreamer;
use App\Http\Streamers\XAccelRedirectStreamer;
use App\Http\Streamers\XSendFileStreamer;
use App\Models\Song;

class SongController extends Controller
{
    /**
     * Play a song.
     *
     * @link https://github.com/phanan/koel/wiki#streaming-music
     *
     * @param $id
     */
    public function play($id)
    {
        switch (env('STREAMING_METHOD')) {
            case 'x-sendfile':
                return (new XSendFileStreamer($id))->stream();
            case 'x-accel-redirect':
                return (new XAccelRedirectStreamer($id))->stream();
            default:
                return (new PHPStreamer($id))->stream();
        }
    }

    /**
     * Get the lyrics of a song.
     *
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLyrics($id)
    {
        return response()->json(Song::findOrFail($id)->lyrics);
    }

    /**
     * Get extra information about a song via Last.fm.
     * 
     * @param string $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInfo($id)
    {
        $song = Song::with('album.artist')->findOrFail($id);

        return response()->json([
            'lyrics' => $song->lyrics,
            'album_info' => $song->album->getInfo(),
            'artist_info' => $song->album->artist->getInfo(),
        ]);
    }
}
