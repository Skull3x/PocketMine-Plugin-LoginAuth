<?php

namespace LoginAuth;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class ShowMessageTask extends PluginTask
{
    private $main;
    private $playerList = [];

    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->main = $main;
    }

    public function onRun($currentTick)
    {
        foreach ($this->playerList as $player) {
            $player->sendMessage("ログインしてください");
        }
    }

    public function addPlayer(Player $player)
    {
        $name = strtolower($player->getName());
        $this->playerList[$name] = $player;
    }

    public function removePlayer(Player $player)
    {
        $name = strtolower($player->getName());
        unset($this->playerList[$name]);
    }
}

?>

