<?php

namespace LoginAuth;

use pocketmine\Player;

require_once("CommandHook.php");

class CommandHookQueue
{
    private $list = [];

    public function makeKey(Player $player) : string
    {
        return $player->getRawUniqueId();
    }

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

    public function clear(Player $player)
    {
        $key = $this->makeKey($player);

        unset($this->list[$key]);
    }
}

?>