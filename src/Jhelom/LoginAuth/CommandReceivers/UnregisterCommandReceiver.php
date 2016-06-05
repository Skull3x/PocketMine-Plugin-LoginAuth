<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

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

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        Main::getInstance()->getLogger()->debug("UnregisterCommandReceiver.execute: ");

        // 削除対象プレイヤー名を取得
        $targetPlayerName = array_shift($args) ?? "";

        // 削除可能か検証
        if ($this->tryUnregister($sender, $targetPlayerName)) {
            // 削除可能なら
            Main::getInstance()->sendMessageResource($sender, "unregisterConfirm", ["name" => $targetPlayerName]);

            // イベントフックを追加
            $invoker->getHookQueue()->enqueue([$this, "execute2"], $sender, $targetPlayerName);

        }
    }

    /*
     * 削除
     */
    public function execute2(CommandInvoker $invoker, CommandSender $sender, array $args, $data)
    {
        // 確認入力を取得
        $input = strtolower(array_shift($args) ?? "");

        // Yなら
        if ($input === "y") {
            // 実際に削除
            $this->unregister($sender, $data);
        } else {
            Main::getInstance()->sendMessageResource($sender, "unregisterCancel");
        }
    }

    /*
     * アカウント削除が可能か検証する（データベースへの反映は行わない）
     */
    public function tryUnregister(CommandSender $sender, string $targetPlayerName) : bool
    {
        if ($targetPlayerName === "") {
            Main::getInstance()->sendMessageResource($sender,
                ["unregisterRequired", "authUsage2"],
                ["name" => $targetPlayerName]);

            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "unregisterNotFound", ["name" => $targetPlayerName]);
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
            Main::getInstance()->sendMessageResource($sender, "unregisterNotFound");
            return false;
        }

        // データベースから削除
        $sql = "DELETE FROM account WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($targetPlayerName), \PDO::PARAM_STR);
        $stmt->execute();

        // 削除完了メッセージを表示
        Main::getInstance()->sendMessageResource($sender, "unregisterSuccessful", ["name" => $targetPlayerName]);

        // プレイヤーを取得
        $player = Main::getInstance()->getServer()->getPlayer($targetPlayerName);

        // プレイヤーを強制ログアウト
        // プレイヤーが存在している場合
        if ($player !== NULL) {
            // プレイヤーがオンラインの場合
            if ($player->isOnline()) {
                // セキュリティスタンプマネージャーから削除
                Main::getInstance()->getLoginCache()->remove($player);

                // プレイヤーを強制ログアウト
                $player->close("", Main::getInstance()->getMessage("unregisterSuccessful", ["name" => $targetPlayerName]));
            }
        }

        return true;
    }

}