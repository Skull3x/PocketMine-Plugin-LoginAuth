<?php

namespace LoginAuth;

use pocketmine\Player;

require_once("CommandHook.php");

class CommandHookQueue
{
    private $list = [];

    /**
     * キーを生成
     * @param Player $player
     * @return string
     */
    public function makeKey(Player $player) : string
    {
        return $player->getRawUniqueId();
    }

    /**
     * プレイヤーに関連付けされたキューが存在すれば true を返す
     *
     * @param Player $player
     * @return bool
     */
    public function exists(Player $player) : bool
    {
        $key = $this->makeKey($player);

        if (!array_key_exists($key, $this->list)) {
            return false;
        }

        if (count($this->list[$key]) === 0) {
            return false;
        }

        return true;
    }

    /**
     * プレイヤーに関連付けされたキーを取り出す。不在の場合は isNull が true の CommandHook を返す
     *
     * @param Player $player
     * @return CommandHook
     */
    public function dequeue(Player $player) : CommandHook
    {
        $key = $this->makeKey($player);

        if (!array_key_exists($key, $this->list)) {
            return new CommandHook(true);
        }

        $hook = array_shift($this->list[$key]);

        if ($hook === NULL) {
            return new CommandHook(true);
        }

        return $hook;
    }

    /**
     * プレイヤーに関連付けしてキューを入れる
     *
     * @param array $callback
     * @param Player $player
     * @param $data
     */
    public function enqueue(array $callback, Player $player, $data)
    {
        $key = $this->makeKey($player);

        if (!array_key_exists($key, $this->list)) {
            $this->list[$key] = [];
        }

        $hook = new CommandHook();
        $hook->player = $player;
        $hook->callback = $callback;
        $hook->data = $data;

        array_push($this->list[$key], $hook);
    }

    /**
     * プレイヤーに関連付けされたキューをクリアする
     *
     * @param Player $player
     */
    public function clear(Player $player)
    {
        $key = $this->makeKey($player);

        unset($this->list[$key]);
    }
}

?>