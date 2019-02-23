<?php

namespace App\Http\Controllers;

use App\Map;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * A heartbeat call to check if your snake server is running.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ping()
    {
        return response()->json();
    }

    /**
     * Signal start of a new game.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Request $request)
    {
        $id = $request->json()->all()['game']['id'];

        if (false === strpos($id, '-')) {
            return response()->json();
        }

        $path = storage_path(sprintf('games/%s', $id));

        if (!is_dir($path)) {
            mkdir($path, 0777);
        }

        return response()->json([
            'color' => '#ef9d04',
            'headType' => 'shades',
            'tailType' => 'skinny',
        ]);
    }

    /**
     * Take a turn within a game.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function move(Request $request)
    {
        $content = $request->getContent();

        $data = json_decode($content, true);

        if ($data['you']['id'] === 'you') {
            return response()->json();
        }

        $id = $data['you']['id'];

        $map = new Map($data['board']['width']);

        $map->setHealth($data['you']['health']);

        foreach ($data['board']['food'] as $food) {
            $map->setFood($food['x'], $food['y']);
        }

        foreach ($data['board']['snakes'] as $snake) {
            $map->setSnake($snake['body'], $snake['id'] !== $id);
        }

        $moves = $map->nextPossibleMove();

        if (empty($moves)) {
            $move = 'dead';
        } else {
            $move = array_first(array_keys($moves));
        }

        $directory = storage_path('games/'.$data['game']['id']);

        @mkdir($directory, 0777, true);

        file_put_contents(sprintf('%s/%04d-%s', $directory, $data['turn'], $move), $content);

        return response()->json(['move' => $move]);
    }

    /**
     * Signal end of a game.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function end()
    {
        return response()->json();
    }
}
