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

        if ($targetPlayerName === "") {
            Main::getInstance()->sendMessageResource($sender,
                ["unregisterRequired", "authUsage2"],
                ["name" => $targetPlayerName]);

            return;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "unregisterNotFound", ["name" => $targetPlayerName]);
            return;
        }

        // 確認入力してくれメッセージを表示
        Main::getInstance()->sendMessageResource($sender, "unregisterConfirm", ["name" => $targetPlayerName]);

        // コマンドフックを追加
        $invoker->getHookQueue()->enqueue([$this, "execute2"], $sender, $targetPlayerName);
    }

    /*
     * 削除
     */
    public function execute2(CommandInvoker $invoker, CommandSender $sender, array $args, $data)
    {
        // 確認入力を取得
        $input = strtolower(array_shift($args) ?? "");

        // Y以外の場合
        if ($input !== "y") {
            Main::getInstance()->sendMessageResource($sender, "unregisterCancel");
            return;
        }

        // 削除対象プレイヤー名をコマンドフックのデータから取得
        $targetPlayerName = $data;

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "unregisterNotFound");
            return;
        }

        // データベースから削除
        $sql = "DELETE FROM account WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($targetPlayerName), \PDO::PARAM_STR);
        $stmt->execute();

        // 削除完了メッセージを表示
        Main::getInstance()->sendMessageResource($sender, "unregisterSuccessful", ["name" => $targetPlayerName]);

        // 以下プレイヤーを強制ログアウトする処理

        // プレイヤーを取得
        $player = Main::getInstance()->getServer()->getPlayer($targetPlayerName);

        // プレイヤー不在している場合
        if ($player === NULL) {
            return;
        }

        // プレイヤーがオンラインではない場合
        if (!$player->isOnline()) {
            return;
        }

        // ログインキャッシュから削除
        Main::getInstance()->getLoginCache()->remove($player);

        // プレイヤーを強制ログアウト
        $player->close("", Main::getInstance()->getMessage("unregisterSuccessful", ["name" => $targetPlayerName]));
    }
}