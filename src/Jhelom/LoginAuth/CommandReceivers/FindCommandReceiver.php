<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
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

    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        $name = array_shift($args) ?? "";

        if ($name === "") {
            Main::getInstance()->sendMessageResource($sender, ["findHelp", "findUsage"]);
            return;
        }

        $sql1 = "SELECT * FROM account WHERE name LIKE :name";
        $stmt1 = Main::getInstance()->preparedStatement($sql1);
        $stmt1->bindValue(":name", "%" . $name . "%", \PDO::PARAM_STR);
        $stmt1->execute();
        $account = $stmt1->fetchObject("Jhelom\\LoginAuth\\Account");

        if ($account === false) {
            Main::getInstance()->sendMessageResource($sender, "accountNotFound", ["name" => $name]);
            return;
        }

        $sql2 = "SELECT * FROM account WHERE name = :name OR ip = :ip OR clientId = :clientId ORDER BY ip, clientId, name LIMIT 100";
        $stmt2 = Main::getInstance()->preparedStatement($sql2);
        $stmt2->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt2->bindValue(":ip", $account->ip, \PDO::PARAM_STR);
        $stmt2->bindValue(":clientId", $account->clientId, \PDO::PARAM_STR);
        $stmt2->execute();
        $accountList = $stmt2->fetchAll(\PDO::FETCH_CLASS, "Jhelom\\LoginAuth\\Account");


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

        $sender->sendMessage($border);

        $s = "| "
            . str_pad("Name", $paddingLarge)
            . " | "
            . str_pad("IP Address", $padding)
            . " | "
            . str_pad("Client ID", $paddingLarge)
            . " | "
            . str_pad("Last Login", $paddingLarge)
            . " |";

        $sender->sendMessage($s);
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
                . str_pad($a->lastLogin, $paddingLarge)
                . " | ";

            $sender->sendMessage($s);
        }

        $sender->sendMessage($border);
    }
}