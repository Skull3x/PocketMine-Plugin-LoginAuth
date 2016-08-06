<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class FindCommandReceiver implements ICommandReceiver
{
    public function getName() : string
    {
        return "find";
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
        $name = array_shift($args) ?? "";

        if ($name === "") {
            Main::getInstance()->sendMessageResource($sender, ["findHelp", "findUsage"]);
            return;
        }

        $account = Main::getInstance()->findAccountByName($name);

        // アカウント不在の場合
        if ($account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "accountNotFound", ["name" => $name]);
            return;
        }

        // 同一IP、同一端末IDを検索
        $sql = "SELECT * FROM account WHERE name = :name OR ip = :ip OR clientId = :clientId ORDER BY ip, clientId, name LIMIT 20";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $account->ip, \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $account->clientId, \PDO::PARAM_STR);
        $stmt->execute();
        $accountList = $stmt->fetchAll(\PDO::FETCH_CLASS, "Jhelom\\LoginAuth\\Account");

        $padding = 15;
        $paddingLarge = 20;

        $border = "+-"
            . str_pad("", $paddingLarge, "-")
            . "-+-"
            . str_pad("", $padding, "-")
            . "-+-"
            . str_pad("", $paddingLarge, "-")
            . "-+-"
            . str_pad("", $paddingLarge, "-")
            . "-+";

        $header = "| "
            . str_pad("Name", $paddingLarge)
            . " | "
            . str_pad("IP Address", $padding)
            . " | "
            . str_pad("Client ID", $paddingLarge)
            . " | "
            . str_pad("Last Login", $paddingLarge)
            . " |";

        $sender->sendMessage($border);
        $sender->sendMessage($header);
        $sender->sendMessage($border);

        foreach ($accountList as $a) {
            $isNameBan = Main::getInstance()->getServer()->getNameBans()->isBanned($name) ? TextFormat::RED : "";
            $isIpBan = Main::getInstance()->getServer()->getIPBans()->isBanned($name) ? TextFormat::RED : "";

            $s = "| "
                . $isNameBan
                . str_pad($a->name, $paddingLarge)
                . TextFormat::WHITE
                . " | "
                . $isIpBan
                . str_pad($a->ip, $padding)
                . TextFormat::WHITE
                . " | "
                . str_pad($a->clientId, $paddingLarge)
                . " | "
                . str_pad($a->lastLoginTime, $paddingLarge)
                . " | ";

            $sender->sendMessage($s);
        }

        $sender->sendMessage($border);
    }
}