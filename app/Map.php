<?php

namespace App;

class Map
{
    /**
     * Map data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Width of map.
     *
     * @var int
     */
    public $width;

    /**
     * My snake info.
     *
     * @var array
     */
    protected $snake = [
        'length' => 1,

        'health' => 0,

        'pos' => [
            'x' => 0,
            'y' => 0,
        ],
    ];

    protected $danger = [];

    /**
     * Constructor.
     *
     * @param int $width
     */
    public function __construct($width)
    {
        $this->width = $width;

        $this->initMap();
    }

    /**
     * Get available next move.
     *
     * @return array
     */
    public function nextPossibleMove()
    {
        $x = $this->snake['pos']['x'];
        $y = $this->snake['pos']['y'];
        
        $moves = [
            'up' => [$x, $y - 1],
            'right' => [$x + 1, $y],
            'down' => [$x, $y + 1],
            'left' => [$x - 1, $y],
        ];

        $moves = $this->removeDeadMoves($moves);

        if (empty($moves)) {
            return $moves;
        }

        $moves = $this->calcThresholds($moves);

        if (!empty($safe = array_diff_key($moves, $this->getDangerMoves($moves)))) {
            if ($this->snake['health'] < 50) {
                $result = $this->getFoodMoves($safe);

                if (!empty($result)) {
                    return $result;
                }
            }

            return $safe;
        }

        if (!empty($head = $this->getHeadMoves($moves))) {
            return $head;
        }

        if (!empty($tail = $this->getTailMoves($moves))) {
            return $tail;
        }

        return $moves;
    }

    /**
     * Filter moves that 100% dead.
     *
     * @param array $moves
     *
     * @return array
     */
    protected function removeDeadMoves(array $moves)
    {
        return array_filter($moves, function ($move) {
            return !in_array(
                $this->getMapContent($move[0], $move[1], true),
                ['wall', 'body'],
                true
            );
        });
    }

    /**
     * Filter moves that will result in closed space.
     *
     * @param array $moves
     *
     * @return array
     */
    protected function calcThresholds(array $moves)
    {
        $map = array_map(function ($row) {
            return array_map(function ($val) {
                return in_array($this->mapping($val), ['space', 'food', 'tail', 'danger'], true) ? 1 : 0;
            }, $row);
        }, $this->data);

        $thresholds = array_map(function ($move) use ($map) {
            $map[$move[0]][$move[1]] = 0;
            return $this->dfsExplore($map, $move[0], $move[1], 10);
        }, $moves);

        foreach ($thresholds as $key => $threshold) {
            $moves[$key][] = $threshold;
        }

        uasort($moves, function ($a, $b) {
            return $a[2] - $b[2];
        });

        return $moves;
    }

    /**
     * DFS explore map.
     *
     * @param array $map
     * @param int $x
     * @param int $y
     * @param int $threshold
     *
     * @return int
     *
     * @todo optimize this
     */
    protected function dfsExplore($map, $x, $y, $threshold)
    {
        if ($threshold === 0) {
            return $threshold;
        }

        $next = [
            'up' => [
                'val' => &$map[$x][$y - 1],
                'x' => $x,
                'y' => $y - 1,
            ],
            'right' => [
                'val' => &$map[$x + 1][$y],
                'x' => $x + 1,
                'y' => $y,
            ],
            'down' => [
                'val' => &$map[$x][$y + 1],
                'x' => $x,
                'y' => $y + 1,
            ],
            'left' => [
                'val' => &$map[$x - 1][$y],
                'x' => $x - 1,
                'y' => $y,
            ],
        ];

        $keys = array_keys($next);

        shuffle($keys);

        foreach($keys as $key) {
            $positions[$key] = $next[$key];
        }

        foreach ($positions as $direction => $position) {
            if ($position['val'] === 1) {
                $position['val'] = 0;
                return $this->dfsExplore($map, $position['x'], $position['y'], $threshold - 1);
            }
        }

        return $threshold;
    }

    /**
     * Get moves which are dangerous.
     *
     * @param array $moves
     *
     * @return array
     */
    protected function getDangerMoves(array $moves)
    {
        return $this->getTypeMoves($moves, ['danger']);
    }

    /**
     * Get moves which are snake head.
     *
     * @param array $moves
     *
     * @return array
     */
    protected function getHeadMoves(array $moves)
    {
        return array_filter($this->getTypeMoves($moves, ['danger']), function ($move) {
            return $this->snake['length'] > $this->danger[$move[0]][$move[1]];
        });
    }

    /**
     * Get moves which are snake tail.
     *
     * @param array $moves
     *
     * @return array
     */
    protected function getTailMoves(array $moves)
    {
        return $this->getTypeMoves($moves, ['tail']);
    }

    /**
     * Get move that will eat food.
     *
     * @param array $moves
     *
     * @return array
     */
    protected function getFoodMoves(array $moves)
    {
        return $this->getTypeMoves($moves, ['food']);
    }

    /**
     * Get specific type moves.
     *
     * @param array $moves
     * @param array $type
     *
     * @return array
     */
    protected function getTypeMoves(array $moves, array $type)
    {
        return array_filter($moves, function ($move) use ($type) {
            return in_array(
                $this->getMapContent($move[0], $move[1], true),
                $type,
                true
            );
        });
    }

    /**
     * Initialize map.
     *
     * @return void
     */
    protected function initMap()
    {
        for ($i = 0; $i < $this->width + 2; ++$i) {
            $boundary = ($i === 0 || $i === ($this->width + 1));

            $this->data[$i] = array_fill(
                0,
                $this->width + 2,
                $boundary ? $this->mapping('wall') : $this->mapping('space')
            );

            if (!$boundary) {
                $this->setMapContent($i, 0, 'wall');
                $this->setMapContent($i, $this->width + 1, 'wall');
            }
        }
    }

    /**
     * Set snake at map.
     *
     * @param array $body
     * @param bool $enemy
     *
     * @return void
     */
    public function setSnake(array $body, $enemy = true)
    {
        for ($i = count($body) - 1; $i >= 0; --$i) {
            $x = $body[$i]['x'] + 1;
            $y = $body[$i]['y'] + 1;

            if ($i === 0) {
                $this->setMapContent($x, $y, 'head');

                if ($enemy) {
                    $this->findAndSetDanger($x, $y, count($body));
                } else {
                    $this->snake['length'] = count($body);
                    $this->snake['pos'] = compact('x', 'y');
                }
            } else if ($i === count($body) - 1) {
                $this->setMapContent($x, $y, 'tail');
            } else {
                $this->setMapContent($x, $y, 'body');
            }
        }
    }

    /**
     * Find enemy next possible move and mark as danger.
     *
     * @param int $x
     * @param int $y
     * @param int $length
     *
     * @return void
     */
    protected function findAndSetDanger($x, $y, $length)
    {
        $positions = [
            [$x, $y - 1],
            [$x + 1, $y],
            [$x, $y + 1],
            [$x - 1, $y],
        ];

        foreach ($positions as $position) {
            list($m, $n) = $position;

            if (in_array($this->getMapContent($m, $n, true), ['space', 'food'], true)) {
                $this->setMapContent($m, $n, 'danger');

                $max = 0;

                if (isset($this->danger[$m][$n])) {
                    $max = $this->danger[$m][$n];
                }

                $this->danger[$m][$n] = max($length, $max);
            }
        }
    }

    /**
     * Set food at map.
     *
     * @param int $x
     * @param int $y
     *
     * @return void
     */
    public function setFood($x, $y)
    {
        $this->setMapContent($x + 1, $y + 1, 'food');
    }

    /**
     * Set snake health.
     *
     * @param int $health
     *
     * @return void
     */
    public function setHealth($health)
    {
        $this->snake['health'] = $health;
    }

    /**
     * Get map content from position.
     *
     * @param int $x
     * @param int $y
     * @param bool $name
     *
     * @return int|string
     */
    protected function getMapContent($x, $y, $name = false)
    {
        $val = $this->data[$x][$y];

        if (!$name) {
            return $val;
        }

        return $this->mapping($val);
    }

    /**
     * Set map content at position.
     *
     * @param int $x
     * @param int $y
     * @param string $type
     *
     * @return void
     */
    protected function setMapContent($x, $y, $type)
    {
        $this->data[$x][$y] = $this->mapping($type);
    }

    /**
     * Draw map.
     *
     * @return void
     */
    public function draw()
    {
        for ($y = 0; $y < $this->width + 2; ++$y) {
            for ($x = 0; $x < $this->width + 2; ++$x) {
                $val = $this->getMapContent($x, $y);

                $name = sprintf('draw%s', ucfirst($this->mapping($val)));

                if (method_exists($this, $name)) {
                    $this->$name($x, $y);
                } else {
                    echo $val;
                }

                echo ' ';
            }

            echo PHP_EOL;
        }
    }

    /**
     * Draw wall.
     *
     * @param int $x
     * @param int $y
     *
     * @return void
     */
    protected function drawWall($x, $y)
    {
        $bound = $this->width + 1;

        if ($x !== 0 && $x !== $bound) {
            echo '═';
        } else {
            if ($y === 0) {
                echo $x === $bound ? '╗' : '╔';
            } else if ($y === $bound) {
                echo $x === $bound ? '╝' : '╚';
            } else {
                echo '║';
            }
        }
    }

    /**
     * Draw space.
     *
     * @return void
     */
    protected function drawSpace()
    {
        echo '·';
    }

    /**
     * Draw food.
     *
     * @return void
     */
    protected function drawFood()
    {
        echo '❤';
    }

    /**
     * Draw head.
     *
     * @return void
     */
    protected function drawHead()
    {
        echo '✦';
    }

    /**
     * Draw body.
     *
     * @return void
     */
    protected function drawBody()
    {
        echo '▫';
    }

    /**
     * Draw tail.
     *
     * @return void
     */
    protected function drawTail()
    {
        echo '▪';
    }

    /**
     * Draw danger.
     *
     * @return void
     */
    protected function drawDanger()
    {
        echo '▵';
    }

    /**
     * Type mapping.
     *
     * @param int|string $key
     *
     * @return int|string
     */
    protected function mapping($key)
    {
        static $mapping = [
            0 => 'wall',
            1 => 'space',
            2 => 'food',
            3 => 'head',
            4 => 'body',
            5 => 'tail',
            6 => 'danger',
        ];

        if (is_string($key)) {
            return array_flip($mapping)[$key];
        }

        return $mapping[$key];
    }
}
