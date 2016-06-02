<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use Jhelom\LoginAuth\MessageThrottling;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class LoginCommandReceiver implements ICommandReceiver
{
    public function getName() : string
    {
        return "login";
    }

    public function isAllowConsole() : bool
    {
        return false;
    }

    public function isAllowPlayer() : bool
    {
        return true;
    }

    public function execute(CommandInvoker $invoker, CommandSender $sender, Command $command, array $args)
    {
        // TODO: Implement execute() method.
    }


    /*
     * ログインする
     */
    public function login(CommandInvoker $invoker, CommandSender $sender, string $password):bool
    {
        // Playerクラスにキャスト
        $player = Main::getInstance()->castToPlayer($sender);

        // 既にログイン認証済みの場合
        if (Main::getInstance()->isAuthenticated($player)) {
            MessageThrottling::send($player, TextFormat::RED . Main::getInstance()->getMessage("loginAlready"));
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            MessageThrottling::send($player, TextFormat::RED . Main::getInstance()->getMessage("register"));
            return false;
        }

        // 空白文字を除去
        $password = trim($password);

        // パスワードを検証
        if (!Main::getInstance()->validatePassword($player, $password, Main::getInstance()->getMessage("passwordRequired"))) {
            return false;
        }

        // パスワードハッシュを生成
        $passwordHash = Main::getInstance()->makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            // パスワード不一致メッセージを表示してリターン
            MessageThrottling::send($player, TextFormat::RED . Main::getInstance()->getMessage("passwordError"));
            return false;
        }

        // データベースのセキュリティスタンプを更新
        $securityStamp = Main::getInstance()->getSecurityStampManager()->makeStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        Main::getInstance()->getSecurityStampManager()->add($player);

        // ログイン成功メッセージを表示
        MessageThrottling::send($player, TextFormat::GREEN . Main::getInstance()->getMessage("loginSuccessful"));

        return true;
    }


}