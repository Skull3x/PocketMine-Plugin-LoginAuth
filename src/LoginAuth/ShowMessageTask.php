<?php

namespace LoginAuth;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;

class ShowMessageTask extends PluginTask
{
    private $main;
    private $playerList = [];

    /**
     * コンストラクタ
     *
     * @param Main $main
     */
    public function __construct(Main $main)
    {
        parent::__construct($main);
        $this->main = $main;
    }

    /**
     * プレイヤーを追加
     *
     * @param Player $player
     */
    public function addPlayer(Player $player)
    {
        $key = $this->makePlayerKey($player);
        $this->playerList[$key] = $player;

        $this->onRun(0);
    }

    /**
     * キーを生成
     * @param Player $player
     * @return string
     */
    private function makePlayerKey(Player $player)
    {
        return $player->getRawUniqueId();
    }

    /**
     * 実行イベント
     *
     * @param $currentTick
     */
    public function onRun($currentTick)
    {
        foreach ($this->playerList as $player) {
            // 登録済みなら
            if ($this->main->isRegistered($player)) {
                // ログインしてもらう
                $player->sendMessage(TextFormat::RED . $this->main->getMessage()->login());
            } else {
                // 未登録ならアカウント登録してもらう
                $player->sendMessage(TextFormat::RED . $this->main->getMessage()->registerFirst());
            }
        }
    }

    /**
     * プレイヤーを削除
     *
     * @param Player $player
     */
    public function removePlayer(Player $player)
    {
        $key = $this->makePlayerKey($player);
        unset($this->playerList[$key]);
    }
}

?>

