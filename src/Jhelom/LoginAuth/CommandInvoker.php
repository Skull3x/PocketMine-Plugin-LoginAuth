<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;
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

    public function getNames() : array
    {
        return array_keys($this->list);
    }

    public function exists(string $name):bool
    {
        $key = strtolower($name);
        return array_key_exists($key, $this->list);
    }

    /*
    * コマンドレシーバーを呼び出す
    * イベントをキャンセルする必要がある場合は　true を返す
    */
    public function execute(CommandSender $sender, string $cmd, array $args) :bool
    {
        $key = strtolower($cmd);

        // コマンドレシーバーのリストにキー（コマンド名）が不在の場合
        if (!array_key_exists($key, $this->list)) {
            return false;
        }

        // コマンドレシーバーを取得
        $receiver = $this->get($key);

        // レシーバーの実行権限がある場合
        if ($this->checkPermission($sender, $receiver)) {
            // レシーバーを実行
            $receiver->execute($sender, $args);
        }

        return true;
    }

    /*
     * 実行権限を検証。実行許可OKならtrueを返す
     */

    private function get(string $name) : ICommandReceiver
    {
        return $this->list[$name];
    }

    /*
     * コマンドレシーバーを取得
     */

    private function checkPermission(CommandSender $sender, ICommandReceiver $receiver) : bool
    {
        // プレイヤーの場合
        if ($sender instanceof Player) {
            // プレイヤー実行許可がある場合
            if ($receiver->isAllowPlayer()) {
                $player = Main::castToPlayer($sender);

                // 認証済みの場合のみ実行許可
                if ($receiver->isAllowAuthenticated()) {
                    if (!Main::getInstance()->isAuthenticated($player)) {
                        Main::getInstance()->sendMessageResource($player, "commandNeedAuth");
                        return false;
                    }
                }

                // OPのみ実行許可がある場合
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
}