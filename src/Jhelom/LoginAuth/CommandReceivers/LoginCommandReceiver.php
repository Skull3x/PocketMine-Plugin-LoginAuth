<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\Account;
use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

/*
 * ログイン
 */
class LoginCommandReceiver implements ICommandReceiver
{
    /*
     * コマンドの名前
     */
    public function getName() : string
    {
        return "login";
    }

    /*
     * コンソール実行許可
     */
    public function isAllowConsole() : bool
    {
        return false;
    }

    /*
     * プレイヤー実行許可
     */
    public function isAllowPlayer() : bool
    {
        return true;
    }

    /*
     * OPのみ実行許可
     */
    public function isAllowOpOnly(): bool
    {
        return false;
    }

    public function isAllowAuthenticated() : bool
    {
        return false;
    }

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        // 引数からパスワードを取得
        $password = trim(array_shift($args) ?? "");

        // Playerクラスにキャスト
        $player = Main::castCommandSenderToPlayer($sender);

        // 既にログイン認証済みの場合
        if (Main::getInstance()->isAuthenticated($player)) {
            Main::getInstance()->sendMessageResource($sender, "loginAlready");
            return;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, ["register", "registerUsage"]);
            return;
        }

        // パスワードが未入力の場合
        if ($password === "") {
            Main::getInstance()->sendMessageResource($sender, "passwordRequired");
            return;
        }

        // パスワードハッシュを生成
        $passwordHash = Account::makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            // パスワード不一致メッセージを表示してリターン
            Main::getInstance()->sendMessageResource($sender, "passwordError");
            return;
        }

        // データベースを更新
        $sql = "UPDATE account SET ip = :ip ,clientId = :clientId, securityStamp = :securityStamp, lastLoginTime = :lastLoginTime WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", Account::makeSecurityStamp($player), \PDO::PARAM_STR);
        $now = new \DateTime();
        $stmt->bindValue(":lastLoginTime", $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();

        // ログインキャッシュに登録
        Main::getInstance()->getLoginCache()->add($player);

        // ログイン成功メッセージを表示
        Main::getInstance()->sendMessageResource($sender, "loginSuccessful");
    }
}