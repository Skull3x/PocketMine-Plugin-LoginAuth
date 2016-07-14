<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\Account;
use Jhelom\LoginAuth\CommandHookManager;
use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

class PasswordChangeCommandReceiver implements ICommandReceiver
{
    public function getName() : string
    {
        return "password";
    }

    public function isAllowConsole() : bool
    {
        return false;
    }

    public function isAllowPlayer() : bool
    {
        return true;
    }

    public function isAllowOpOnly(): bool
    {
        return false;
    }

    public function isAllowAuthenticated() : bool
    {
        return true;
    }

    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        $newPassword = trim(array_shift($args) ?? "");

        // Playerクラスにキャスト
        $player = Main::getInstance()->castToPlayer($sender);

        // パスワードが不適合の場合
        if (Main::getInstance()->isInvalidPassword($player, $newPassword, "changePasswordUsage")) {
            return;
        }

        // 「確認のためもう一度パスワードを入力して」メッセージを表示
        Main::getInstance()->sendMessageResource($sender, "changePasswordConfirm");

        // コマンドフックを登録
        CommandHookManager::getInstance()->enqueue([$this, "execute2"], $sender, $newPassword);
    }

    /*
     * データベースにアカウントを登録する
     */
    public function execute2(/** @noinspection PhpUnusedParameterInspection */
        CommandInvoker $invoker, CommandSender $sender, array $args, $data)
    {
        // 確認用パスワードを引数から取得
        $password = trim(array_shift($args) ?? "");

        // 確認用パスワードが違う場合
        if ($data !== $password) {
            Main::getInstance()->sendMessageResource($sender, "changePasswordError");
            return;
        }

        // Playerクラスにキャスト
        $player = Main::getInstance()->castToPlayer($sender);

        //　データベースに登録
        $sql = "UPDATE account SET passwordHash = :passwordHash WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", Account::makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->execute();

        // アカウント登録成功メッセージを表示
        Main::getInstance()->sendMessageResource($player, "changePasswordSuccessful", ["password" => $password]);
    }
}