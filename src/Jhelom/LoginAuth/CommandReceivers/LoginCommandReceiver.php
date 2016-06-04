<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

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
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("loginAlready"));
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("register"));
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
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("passwordError"));
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
        $sender->sendMessage(TextFormat::GREEN . Main::getInstance()->getMessage("loginSuccessful"));

        return true;
    }


}