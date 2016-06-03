<?php

namespace Jhelom\LoginAuth;


use pocketmine\command\CommandSender;
use pocketmine\Player;

class CommandHookQueue
{
    private $list = [];

    /*
     * キーを生成
     */
    public function makeKey(CommandSender $sender) : string
    {
        // Player の場合
        if ($sender instanceof Player) {
            // キャストして
            $player = Main::castCommandSenderToPlayer($sender);

            // ユニークIDを返す
            return $player->getRawUniqueId();
        } else {
            // Player ではない場合、名前を返す
            return $sender->getName();
        }
    }

    /*
     * キューが存在すれば true を返す
     *
     */
    public function exists(CommandSender $player) : bool
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

    /*
     * プレイヤーに関連付けされたキーを取り出す。不在の場合は isNull が true の CommandHook を返す
     *
     */
    public function dequeue(CommandSender $sender) : CommandHook
    {
        $key = $this->makeKey($sender);

        if (!array_key_exists($key, $this->list)) {
            return new CommandHook(true);
        }

        $hook = array_shift($this->list[$key]);

        if ($hook === NULL) {
            return new CommandHook(true);
        }

        return $hook;
    }

    /*
     * キューに入れる
     */
    public function enqueue(array $callback, CommandSender $sender, $data = NULL)
    {
        $key = $this->makeKey($sender);

        if (!array_key_exists($key, $this->list)) {
            $this->list[$key] = [];
        }

        $hook = new CommandHook();
        $hook->callback = $callback;
        $hook->data = $data;

        array_push($this->list[$key], $hook);
    }

    /*
     *  キューをクリアする
     */
    public function clear(CommandSender $sender)
    {
        $key = $this->makeKey($sender);

        unset($this->list[$key]);
    }
}

?>