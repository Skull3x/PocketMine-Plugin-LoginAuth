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
        $name = $receiver->getName();

        if (array_key_exists($name)) {
            Server::getInstance()->getLogger()->warning("Dispatcher.add: キー重複 " . $name);
        }

        $this->list[$name] = $receiver;
    }

    /*
     * コマンドを処理する、正常に処理が完了した場合 true を返す
     */
    public function invoke(CommandSender $sender, Command $command, array $args):bool
    {
        $commandName = $command->getName();

        // キーが不在の場合
        if (!array_key_exists($commandName, $this->list)) {
            return false;
        }

        $receiver = $this->getReceiver($commandName);

        if ($sender instanceof Player) {
            if (!$receiver->isAllowPlayer()) {
                MessageThrottling::send($sender, TextFormat::RED . $this->getMessage("commandAtPlayer"));
                return false;
            }
        } else {
            if (!$receiver->isAllowConsole()) {
                MessageThrottling::send($sender, TextFormat::RED . $this->getMessage("commandAtConsole"));
                return false;
            }
        }

        $receiver->execute($this, $sender, $command, $args);

        return true;
    }

    private function getReceiver(string $commandName) : ICommandReceiver
    {
        return $this->list[$commandName];
    }

}