<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\Account;
use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

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

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        $password = array_shift($args) ?? "";
        $this->login($sender, $password);
    }

    /*
     * ログインする
     */
    public function login(CommandSender $sender, string $password):bool
    {
        // Playerクラスにキャスト
        $player = Main::castCommandSenderToPlayer($sender);

        // 既にログイン認証済みの場合
        if (Main::getInstance()->isAuthenticated($player)) {
            Main::getInstance()->sendMessageResource($sender, "loginAlready");
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, ["register", "registerUsage"]);
            return false;
        }

        // 空白文字を除去
        $password = trim($password);

        // パスワードが未入力の場合
        if ($password === "") {
            Main::getInstance()->sendMessageResource($sender, "passwordRequired");
            return false;
        }

        // パスワードハッシュを生成
        $passwordHash = Account::makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            // パスワード不一致メッセージを表示してリターン
            Main::getInstance()->sendMessageResource($sender, "passwordError");
            return false;
        }

        // データベースのセキュリティスタンプを更新
        $securityStamp = Account::makeSecurityStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        Main::getInstance()->getLoginCache()->add($player);

        // ログイン成功メッセージを表示
        Main::getInstance()->sendMessageResource($sender, "loginSuccessful");

        return true;
    }


}