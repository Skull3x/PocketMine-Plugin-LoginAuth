<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\Account;
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

    public function execute(CommandSender $sender, array $args)
    {
        $newPassword = trim(array_shift($args) ?? "");

        // Playerクラスにキャスト
        $player = Main::getInstance()->castToPlayer($sender);

        // パスワードが不適合の場合
        if (Main::getInstance()->isInvalidPassword($player, $newPassword, "changePasswordUsage")) {
            return;
        }

        //　データベースに登録
        $sql = "UPDATE account SET passwordHash = :passwordHash WHERE name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", Account::makePasswordHash($newPassword), \PDO::PARAM_STR);
        $stmt->execute();

        // アカウント登録成功メッセージを表示
        Main::getInstance()->sendMessageResource($player, "changePasswordSuccessful", ["password" => $newPassword]);
    }
}