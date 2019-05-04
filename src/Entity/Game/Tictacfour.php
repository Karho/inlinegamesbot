<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Entity\Game;

use jacklul\inlinegamesbot\Entity\Game;
use jacklul\inlinegamesbot\Exception\StorageException;
use jacklul\inlinegamesbot\Helper\Utilities;
use Spatie\Emoji\Emoji;

/**
 * Tic-Tac-Four
 */
class Tictacfour extends Tictactoe
{
    /**
     * Game unique ID
     *
     * @var string
     */
    protected static $code = 'ttf';

    /**
     * Game name / title
     *
     * @var string
     */
    protected static $title = 'Tic-Tac-Four';

    /**
     * Game description
     *
     * @var string
     */
    protected static $description = 'Tic-tac-four is a game for two players who take turns marking the spaces in a 6×5 grid. Each player chooses X or O in his turn, and may change his minds from turns to turns. The winner is the one who finishes to connect 4-in-a-row, but loses when your opponent can connect with the aternative immediately after that.';

    /**
     * Game thumbnail image
     *
     * @var string
     */
    protected static $image = 'https://i.imgur.com/t4FQuSz.png';

    /**
     * Order on the games list
     *
     * @var int
     */
    protected static $order = 2;

    /**
     * Base starting board
     *
     * @var array
     */
    protected $board = [
        ['', '', '', '', '', ''],
        ['', '', '', '', '', ''],
        ['', '', '', '', '', ''],
        ['', '', '', '', '', ''],
        ['', '', '', '', '', ''],
    ];

    /**
     * Game handler
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse|mixed
     *
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \jacklul\inlinegamesbot\Exception\StorageException
     */
    protected function gameAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $data = &$this->data['game_data'];

        $this->defineSymbols();

        $callbackquery_data = $this->manager->getUpdate()->getCallbackQuery()->getData();
        $callbackquery_data = explode(';', $callbackquery_data);

        $command = $callbackquery_data[1];

        $args = null;
        if (isset($callbackquery_data[2])) {
            $args = explode('-', $callbackquery_data[2]);
        }

        if ($command === 'start') {
            if (isset($data['settings']) && $data['settings']['X'] == 'host') {
                $data['settings']['X'] = 'guest';
                $data['settings']['O'] = 'host';
            } else {
                $data['settings']['X'] = 'host';
                $data['settings']['O'] = 'guest';
            }

            $data['current_turn'] = 'X';
            $data['board'] = $this->board;

            Utilities::debugPrint('Game initialization');
        } elseif ($args === null) {
            Utilities::debugPrint('No move data received');
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!"), true);
        }

        if ($this->getCurrentUserId() !== $this->getUserId($data['settings'][$data['current_turn']]) && $command !== 'start') {
            return $this->answerCallbackQuery(__("It's not your turn!"), true);
        }

        if (isset($args) && isset($data['board'][$args[0]][$args[1]]) && $data['board'][$args[0]][$args[1]] !== '') {
            return $this->answerCallbackQuery(__("Invalid move!"), true);
        }

        $this->max_y = count($data['board']);
        $this->max_x = count($data['board'][0]);

        if (isset($args)) {
            if ($data['current_turn'] == 'X') {
                $data['board'][$args[0]][$args[1]] = 'X';
                $data['current_turn'] = 'O';
            } elseif ($data['current_turn'] == 'O') {
                $data['board'][$args[0]][$args[1]] = 'O';
                $data['current_turn'] = 'X';
            } else {
                Utilities::debugPrint('Invalid move data: ' . ($args[0]) . ' - ' . ($args[1]));

                return $this->answerCallbackQuery(__("Invalid move!"), true);
            }

            Utilities::debugPrint($data['current_turn'] . ' placed at ' . ($args[1]) . ' - ' . ($args[0]));
        }

        $isOver = $this->isGameOver($data['board']);
        $gameOutput = '';

        if (!empty($isOver) && in_array($isOver, ['X', 'O'])) {
            $isOverPenultimate = $isOver;
            $gameOutput = Emoji::doubleExclamationMark() . ' <b>' . __("{PLAYER} probably won! Wait the revenge", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings'][$isOver]) . '<b>']) . '</b>';
        } elseif ($isOver == 'T') {
            $gameOutput = Emoji::chequeredFlag() . ' <b>' . __("Game ended with a draw!") . '</b>';
        }
        
        if (!empty($isOver) && in_array($isOver, ['X', 'O', 'T'])) {
            if ($isOver != 'T') {
                $gameOutput += . '\\n Last turn: ' . $this->getUserMention($data['settings'][$data['current_turn']]) . ' (' . $this->symbols[$data['current_turn']] . ')';
                if ($isOver != $isOverPenultimate) {
                    $gameOutput = Emoji::trophy() . ' <b>' . __("{PLAYER} won and revenged!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings'][$isOver]) . '<b>']) . '</b>';
                } else {
                    $gameOutput = Emoji::trophy() . ' <b>' . __("{PLAYER} won finally!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings'][$isOver]) . '<b>']) . '</b>';
                }
            }
            $data['current_turn'] = 'E';
        } else {
            $gameOutput = Emoji::blackRightwardsArrow() . ' ' . $this->getUserMention($data['settings'][$data['current_turn']]) . ' (' . $this->symbols[$data['current_turn']] . ')';
        }
        
        if ($this->saveData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . ' (' . (($data['settings']['X'] == 'host') ? $this->symbols['X'] : $this->symbols['O']) . ')' . ' ' . Emoji::squaredVs() . ' ' . $this->getUserMention('guest') . ' (' . (($data['settings']['O'] == 'guest') ? $this->symbols['O'] : $this->symbols['X']) . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                $this->gameKeyboard($data['board'], $isOver)
            );
        } else {
            throw new StorageException();
        }
    }

    /**
     * Define game symbols (emojis)
     */
    protected function defineSymbols()
    {
        $this->symbols['empty'] = '.';

        $this->symbols['X'] = Emoji::crossMark();
        $this->symbols['O'] = Emoji::heavyLargeCircle();

        $this->symbols['X_won'] = $this->symbols['X'];
        $this->symbols['O_won'] = $this->symbols['O'];

        $this->symbols['X_lost'] = Emoji::heavyMultiplicationX();
        $this->symbols['O_lost'] = Emoji::radioButton();
    }

    /**
     * Check whenever game is over
     *
     * @param array $board
     *
     * @return string
     */
    protected function isGameOver(array &$board)
    {
        $empty = 0;
        for ($x = 0; $x <= $this->max_x; $x++) {
            for ($y = 0; $y <= $this->max_y; $y++) {
                if (isset($board[$x][$y]) && $board[$x][$y] == '') {
                    $empty++;
                }

                if (isset($board[$x][$y]) && isset($board[$x][$y + 1]) && isset($board[$x][$y + 2]) && isset($board[$x][$y + 3])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x][$y + 1] && $board[$x][$y] == $board[$x][$y + 2] && $board[$x][$y] == $board[$x][$y + 3]) {
                        $winner = $board[$x][$y];
                        $board[$x][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x][$y + 3] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x + 1][$y]) && isset($board[$x + 2][$y]) && isset($board[$x + 3][$y])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y] && $board[$x][$y] == $board[$x + 2][$y] && $board[$x][$y] == $board[$x + 3][$y]) {
                        $winner = $board[$x][$y];
                        $board[$x + 1][$y] = $board[$x][$y] . '_won';
                        $board[$x + 2][$y] = $board[$x][$y] . '_won';
                        $board[$x + 3][$y] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x + 1][$y + 1]) && isset($board[$x + 2][$y + 2]) && isset($board[$x + 3][$y + 3])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y + 1] && $board[$x][$y] == $board[$x + 2][$y + 2] && $board[$x][$y] == $board[$x + 3][$y + 3]) {
                        $winner = $board[$x][$y];
                        $board[$x + 1][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x + 2][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x + 3][$y + 3] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x - 1][$y + 1]) && isset($board[$x - 2][$y + 2]) && isset($board[$x - 3][$y + 3])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x - 1][$y + 1] && $board[$x][$y] == $board[$x - 2][$y + 2] && $board[$x][$y] == $board[$x - 3][$y + 3]) {
                        $winner = $board[$x][$y];
                        $board[$x - 1][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x - 2][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x - 3][$y + 3] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }
            }
        }

        if ($empty == 0) {
            return 'T';
        }

        return null;
    }
}
