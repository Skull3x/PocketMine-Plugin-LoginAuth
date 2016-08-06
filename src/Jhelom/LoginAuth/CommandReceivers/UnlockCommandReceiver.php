<?php
/**
 * Created by PhpStorm.
 * User: ztt
 * Date: 2016/07/10
 * Time: 22:11
 */

namespace Jhelom\LoginAuth\CommandReceivers;


use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

class UnlockCommandReceiver implements ICommandReceiver
{

    public function getName() : string
    {
        return "unlock";
    }

    public function isAllowConsole() : bool
    {
        return true;
    }

    public function isAllowPlayer() : bool
    {
        return false;
    }

    public function isAllowOpOnly(): bool
    {
        return false;
    }

    public function isAllowAuthenticated() : bool
    {
        return false;
    }

    public function execute(CommandSender $sender, array $args)
    {
        // プレイヤー名を取得
        $name = strtolower(array_shift($args) ?? "");

        if ($name === "") {
            Main::getInstance()->sendMessageResource($sender, ["addHelp", "authUsage5"]);
            return;
        }

        $account = Main::getInstance()->findAccountByName($name);

        // アカウントが不在の場合
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "accountNotFound", ["name" => $name]);
            return;
        }

        //　データベース更新
        $sql = "UPDATE account SET clientId = :clientId where name = :name";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", "", \PDO::PARAM_STR); // 端末IDを空白にする
        $stmt->execute();

        Main::getInstance()->sendMessageResource($sender, "unlockSuccess",
            [
                "name" => $name
            ]);
    }
}