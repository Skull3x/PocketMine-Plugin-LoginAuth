<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

/*
 * コマンドインボーカー
 */

class CommandInvoker
{
    // コマンドのプレフィックス
    const COMMAND_PREFIX = "/";

    // コマンドレシーバーのリスト
    private $list = [];

    // フック
    private static $hookQueue;

    /*
     * コンストラクタ
     */
    public function __construct()
    {
    }

    public static function getHookQueue() : CommandHookQueue
    {
        if (self::$hookQueue === NULL) {
            self::$hookQueue = new CommandHookQueue();
        }

        return self::$hookQueue;
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
     * コンソール（サーバー）のコマンドを処理
     */
    public function invokeServerCommand(ServerCommandEvent $event)
    {
        Main::getInstance()->getLogger()->debug("invokeServerCommand: " . $event->getSender()->getName() . ": " . $event->getCommand());

        $sender = $event->getSender();
        $args = explode(" ", $event->getCommand());

        if ($this->invoke($sender, $args)) {
            $event->setCancelled(true);
        }
    }

    /*
     * プレイヤーのコマンドを処置
     */
    public function invokePlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        Main::getInstance()->getLogger()->debug("invokePlayerCommand: " . $event->getPlayer()->getName() . ": " . $event->getMessage());

        $sender = $event->getPlayer();
        $args = explode(" ", $event->getMessage());

        if ($this->invoke($sender, $args, true)) {
            $event->setCancelled(true);
        }
    }

    /*
    * レシーバーを呼び出す
    * イベントをキャンセルする必要がある場合は　true を返す
    */
    public function invoke(CommandSender $sender, array $args, bool $useCommandPrefix = false) :bool
    {
        $hook = self::getHookQueue()->dequeue($sender);

        if (!$hook->isNull) {
            Main::getInstance()->getLogger()->debug("call hook");
            call_user_func($hook->callback, $this, $sender, $args, $hook->data);
            return true;
        }

        $command = array_shift($args) ?? "";

        if ($useCommandPrefix) {
            if (strpos($command, self::COMMAND_PREFIX) !== 0) {
                return false;
            }

            $command = ltrim($command, self::COMMAND_PREFIX);
        }

        if (!array_key_exists($command, $this->list)) {
            return false;
        }

        $receiver = $this->getReceiver($command);

        if ($this->validate($sender, $receiver)) {
            $receiver->execute($this, $sender, $args);
        }

        return true;
    }

    private function validate(CommandSender $sender, ICommandReceiver $receiver) : bool
    {
        if (Main::isPlayer($sender)) {
            if ($receiver->isAllowPlayer()) {
                $player = Main::castCommandSenderToPlayer($sender);
                if ($receiver->isAllowOpOnly()) {
                    if ($player->isOp()) {
                        return true;
                    } else {
                        MessageThrottling::send($sender, TextFormat::RED . Main::getInstance()->getMessage("commandAtOpOnly"), true);
                        return false;
                    }
                } else {
                    return true;
                }
            } else {
                MessageThrottling::send($sender, TextFormat::RED . Main::getInstance()->getMessage("commandAtConsole"), true);
                return false;
            }
        } else {
            if ($receiver->isAllowConsole()) {
                return true;
            } else {
                MessageThrottling::send($sender, TextFormat::RED . Main::getInstance()->getMessage("commandAtPlayer"), true);
                return false;
            }
        }
    }

    private function getReceiver(string $name) : ICommandReceiver
    {
        return $this->list[$name];
    }

    public function existsCommand(string $command) : bool
    {
        return array_key_exists($command, $this->list);
    }
}