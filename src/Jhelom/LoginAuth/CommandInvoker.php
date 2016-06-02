<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

/*
 * コマンドインボーカー
 */

class CommandInvoker
{
    private $main;

    // コマンドレシーバーのリスト
    private $list = [];

    // フック
    private $hookQueue;

    /*
     * コンストラクタ
     */
    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->hookQueue = new CommandHookQueue();
    }

    public function getMain() : Main
    {
        return $this->main;
    }

    public function getHookQueue() : CommandHookQueue
    {
        return $this->hookQueue;
    }

    public function add(ICommandReceiver $receiver)
    {
        $name = strtolower($receiver->getName());

        if (array_key_exists($name, $this->list)) {
            Server::getInstance()->getLogger()->warning("Dispatcher.add: キー重複 " . $name);
        }

        $this->list[$name] = $receiver;
    }

    /*
     * コマンドを処理する、正常に処理が完了した場合 true を返す
     */
    public function invoke(CommandSender $sender, Command $command, array $args):bool
    {
        $name = strtolower($command->getName());

        // キーが不在の場合
        if (!array_key_exists($name, $this->list)) {
            return false;
        }

        $receiver = $this->getReceiver($name);

        // sender が Player の場合
        if ($sender instanceof Player) {
            // playerによる実行が禁止の場合
            if (!$receiver->isAllowPlayer()) {
                MessageThrottling::send($sender, TextFormat::RED . $this->getMain()->getMessage("commandAtPlayer"));
                return true;
            }
        } else {
            // コンソールによる実行が禁止の場合
            if (!$receiver->isAllowConsole()) {
                MessageThrottling::send($sender, TextFormat::RED . $this->getMain()->getMessage("commandAtConsole"));
                return true;
            }
        }

        $receiver->execute($this, $sender, $command, $args);

        return true;
    }

    private function getReceiver(string $name) : ICommandReceiver
    {
        return $this->list[$name];
    }

}