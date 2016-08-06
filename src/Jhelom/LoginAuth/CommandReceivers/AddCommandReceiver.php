<?php

namespace Jhelom\LoginAuth\CommandReceivers;


use Jhelom\LoginAuth\Account;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

class AddCommandReceiver implements ICommandReceiver
{
    public function getName() : string
    {
        return "add";
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
            Main::getInstance()->sendMessageResource($sender, ["addHelp", "addUsage"]);
            return;
        }

        $account = Main::getInstance()->findAccountByName($name);

        // 同じ名前のアカウントが存在する場合
        if (!$account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "registerExists", ["name" => $name]);
            return;
        }

        // パスワードを取得
        $password = array_shift($args) ?? "";

        // パスワードが未指定の場合
        if ($password === "") {
            // ランダムなパスワードを生成
            $password = substr(hash("sha256", mt_rand()), 0, 6);
        }

        // パスワードが不適合の場合
        if (Main::getInstance()->isInvalidPassword($sender, $password)) {
            return;
        }

        //　データベースに登録
        $sql = "INSERT INTO account (name, passwordHash) VALUES (:name, :passwordHash)";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", Account::makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->execute();

        Main::getInstance()->sendMessageResource($sender, "addSuccessful",
            [
                "name" => $name,
                "password" => $password
            ]);
    }
}