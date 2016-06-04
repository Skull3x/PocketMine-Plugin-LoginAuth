<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class UnregisterCommandReceiver implements ICommandReceiver
{
    /*
     * コマンドの名前
     */
    public function getName() : string
    {
        return "unregister";
    }

    /*
     * コンソール実行許可
     */
    public function isAllowConsole() : bool
    {
        return true;
    }

    /*
     * プレイヤー実行許可
     */
    public function isAllowPlayer() : bool
    {
        return false;
    }

    /*
     * OPのみ実行許可
     */
    public function isAllowOpOnly(): bool
    {
        return true;
    }

    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        Main::getInstance()->getLogger()->debug("UnregisterCommandReceiver.execute: ");

        $targetPlayerName = array_shift($args) ?? "";

        if ($this->tryUnregister($sender, $targetPlayerName)) {
            $msg = TextFormat::YELLOW . Main::getInstance()->getMessage("unregisterConfirm", ["name" => $targetPlayerName]);
            $sender->sendMessage($msg);
            $invoker->getHookQueue()->enqueue([$this, "execute2"], $sender, $targetPlayerName);

        }
    }

    public function execute2(CommandInvoker $invoker, CommandSender $sender, array $args, $data)
    {
        $input = strtolower(array_shift($args) ?? "");

        if ($input === "y") {
            $this->unregister($sender, $data);
        } else {
            $sender->sendMessage(TextFormat::YELLOW . Main::getInstance()->getMessage("unregisterCancel"));
        }
    }

    /*
     * アカウント削除が可能か検証する（データベースへの反映は行わない）
     */
    public function tryUnregister(CommandSender $sender, string $targetPlayerName) : bool
    {
        if ($targetPlayerName === "") {
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("unregisterRequired", ["name" => $targetPlayerName]));
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("authUsage2"));
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("unregisterNotFound", ["name" => $targetPlayerName]));
            return false;
        }

        return true;
    }

    /*
     * アカウントを削除する
     */
    public function unregister(CommandSender $sender, string $targetPlayerName) :bool
    {
        if (!$this->tryUnregister($sender, $targetPlayerName)) {
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("unregisterNotFound"));
            return false;
        }

        // データベースから削除
        $sql = "DELETE FROM account WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($targetPlayerName), \PDO::PARAM_STR);
        $stmt->execute();

        // 削除完了メッセージを表示
        $sender->sendMessage(TextFormat::GREEN . Main::getInstance()->getMessage("unregisterSuccessful", ["name" => $targetPlayerName]));

        // プレイヤーを取得
        $player = Main::getInstance()->getServer()->getPlayer($targetPlayerName);

        // プレイヤーが存在している場合
        if ($player !== NULL) {
            // プレイヤーがオンラインの場合
            if ($player->isOnline()) {
                // セキュリティスタンプマネージャーから削除
                Main::getInstance()->getSecurityStampManager()->remove($player);

                // プレイヤーを強制ログアウト
                $player->close("", Main::getInstance()->getMessage("unregisterSuccessful", ["name" => $targetPlayerName]));
            }
        }

        return true;
    }

}