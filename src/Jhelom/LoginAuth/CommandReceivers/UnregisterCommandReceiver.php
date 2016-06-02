<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\MessageThrottling;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class UnregisterCommandReceiver implements ICommandReceiver
{
    public function getName() : string
    {
        return "unregister";
    }

    public function isAllowConsole() : bool
    {
        return true;
    }

    public function isAllowPlayer() : bool
    {
        return false;
    }

    public function execute(CommandInvoker $invoker, CommandSender $sender, Command $command, array $args)
    {
        $targetPlayerName = array_shift($args) ?? "";

        if ($this->tryUnregister($sender, $targetPlayerName)) {

        }
    }

    /*
 * アカウント削除が可能か検証する（データベースへの反映は行わない）
 */
    public function tryUnregister(CommandSender $sender, string $targetPlayerName) : bool
    {
        // アカウントを検索
        $account = $this->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            MessageThrottling::send($sender, TextFormat::RED . $this->getMessage("unregisterNotFound", ["name" => $targetPlayerName]));
            return false;
        }

        return true;
    }

    /*
     * アカウントを削除する
     */
    public function unregister(CommandSender $sender, string $targetPlayerName) :bool
    {
        if (!$this->tryUnregister($sender, $targetPlayerName)) {
            return false;
        }

        // アカウントを検索
        $account = $this->findAccountByName($targetPlayerName);

        // アカウントが不在の場合
        if ($account->isNull) {
            MessageThrottling::send($sender, TextFormat::RED . $this->getMessage("unregisterNotFound"));
            return false;
        }

        // データベースから削除
        $sql = "DELETE account WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($targetPlayerName), \PDO::PARAM_STR);
        $stmt->execute();

        // 削除完了メッセージを表示
        MessageThrottling::send($sender, TextFormat::GREEN . $this->getMessage("unregisterSuccessful", ["name" => $targetPlayerName]));

        // プレイヤーを取得
        $player = $this->getServer()->getPlayer($targetPlayerName);

        // プレイヤーが存在している場合
        if ($player !== NULL) {
            // プレイヤーがオンラインの場合
            if ($player->isOnline()) {
                // セキュリティスタンプマネージャーから削除
                $this->getSecurityStampManager()->remove($player);

                // プレイヤーを強制ログアウト
                $player->close("", $this->getMessage("unregisterSuccessful", ["name" => $targetPlayerName]));
            }
        }

        return true;
    }

}