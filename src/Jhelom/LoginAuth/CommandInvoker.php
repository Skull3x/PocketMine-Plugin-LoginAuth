<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\Player;
use pocketmine\Server;

/*
 * コマンドインボーカー
 */
class CommandInvoker
{
    // コマンドのプレフィックス
    const COMMAND_PREFIX = "/";

    // コマンドレシーバーのリスト
    private $list = [];

    /*
     * コマンドレシーバーを登録
     */
    public function add(ICommandReceiver $receiver)
    {
        $name = strtolower($receiver->getName());

        // 重複登録はプログラムのミスの疑いあり
        if (array_key_exists($name, $this->list)) {
            // 警告ログを出力
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
     * プレイヤーのコマンドを処理
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
    * コマンドレシーバーを呼び出す
    * イベントをキャンセルする必要がある場合は　true を返す
    */
    public function invoke(CommandSender $sender, array $args, bool $useCommandPrefix = false) :bool
    {
        $hook = CommandHookManager::getInstance()->dequeue($sender);

        if (!$hook->isNull) {
            Main::getInstance()->getLogger()->debug("call hook");
            call_user_func($hook->callback, $this, $sender, $args, $hook->data);
            return true;
        }

        // 引数からコマンドを取得
        $command = array_shift($args) ?? "";

        // コマンドプレフィックスを使う場合
        if ($useCommandPrefix) {
            // 文字列の先頭がコマンドプレフィックスではない場合
            if (strpos($command, self::COMMAND_PREFIX) !== 0) {
                return false;
            }

            // コマンドプレフィックスを除去
            $command = ltrim($command, self::COMMAND_PREFIX);
        }

        // コマンドレシーバーのリストにキー（コマンド名）が不在の場合
        if (!array_key_exists($command, $this->list)) {
            return false;
        }

        // コマンドレシーバーを取得
        $receiver = $this->get($command);

        // レシーバーの実行権限がある場合
        if ($this->checkPermission($sender, $receiver)) {
            // レシーバーを実行
            $receiver->execute($this, $sender, $args);
        }

        return true;
    }

    /*
     * 実行権限を検証。実行許可OKならtrueを返す
     */
    private function checkPermission(CommandSender $sender, ICommandReceiver $receiver) : bool
    {
        // プレイヤーの場合
        if ($sender instanceof Player) {
            // プレイヤー実行権限がある場合
            if ($receiver->isAllowPlayer()) {
                $player = Main::castCommandSenderToPlayer($sender);
                // OPのみ実行権限がある場合
                if ($receiver->isAllowOpOnly()) {
                    // プレイヤーがOPの場合
                    if ($player->isOp()) {
                        return true;
                    } else {
                        Main::getInstance()->sendMessageResource($sender, "commandAtOpOnly");
                        return false;
                    }
                } else {
                    return true;
                }
            } else {
                Main::getInstance()->sendMessageResource($sender, "commandAtConsole");
                return false;
            }
        } else {
            // プレイヤー以外の場合
            // コンソール実行許可がある場合
            if ($receiver->isAllowConsole()) {
                return true;
            } else {
                Main::getInstance()->sendMessageResource($sender, "commandAtPlayer");
                return false;
            }
        }
    }

    /*
     * コマンドレシーバーを取得
     */
    private function get(string $name) : ICommandReceiver
    {
        return $this->list[$name];
    }
}