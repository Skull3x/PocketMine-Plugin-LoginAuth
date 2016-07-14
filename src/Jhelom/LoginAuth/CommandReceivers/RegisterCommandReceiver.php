<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\Account;
use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;
use pocketmine\Player;

/*
 * アカウント登録
 */

class RegisterCommandReceiver implements ICommandReceiver
{
    /*
     * コマンド名
     */
    public function getName() : string
    {
        return "register";
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

    public function isAllowAuthenticated() : bool
    {
        return false;
    }

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        $password = trim(array_shift($args) ?? "");

        // Playerクラスにキャスト
        $player = Main::getInstance()->castToPlayer($sender);

        // 既にログイン認証済みの場合
        if (Main::getInstance()->isAuthenticated($player)) {
            Main::getInstance()->sendMessageResource($sender, "loginAlready");
            return;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // 同じ名前のアカウントが存在する場合
        if (!$account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "registerExists", ["name" => $player->getName()]);
            return;
        }

        // 名前が不適合の場合
        if ($this->isInvalidName($player)) {
            return;
        }

        // パスワードが不適合の場合
        if (Main::getInstance()->isInvalidPassword($player, $password, "registerUsage")) {
            return;
        }

        // 端末毎アカウント数が不適合の場合
        if ($this->isInvalidAccountSlot($player)) {
            return;
        }

        //　データベースに登録
        $sql = "INSERT INTO account (name, clientId, ip, passwordHash, securityStamp, lastLoginTime) VALUES (:name, :clientId, :ip, :passwordHash, :securityStamp, :lastLoginTime)";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", Account::makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", Account::makeSecurityStamp($player), \PDO::PARAM_STR);
        $now = new \DateTime();
        $stmt->bindValue(":lastLoginTime", $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();

        // ログインキャッシュに登録
        Main::getInstance()->getLoginCache()->add($player);

        // アカウント登録成功メッセージを表示
        Main::getInstance()->sendMessageResource($player, "registerSuccessful", ["password" => $password]);
    }

    /*
     * 端末毎アカウント登録数が不適合ならtrueを返す
     */
    private function isInvalidAccountSlot(Player $player) : bool
    {
        // 端末IDをもとにデータベースからアカウント一覧を取得
        $accountList = Main::getInstance()->findAccountListByClientId($player->getClientId());

        // アカウント一覧の数
        $accountListCount = count($accountList);

        // 端末毎のアカウント上限数を取得（最低１以上になるように補正）
        $accountSlotMax = max(1, Main::getInstance()->getConfig()->get("accountSlot"));

        // アカウント上限以上の場合
        if ($accountSlotMax <= $accountListCount) {
            // 名前一覧を組み立てるための配列
            $nameList = [];

            // アカウント一覧をループ
            foreach ($accountList as $account) {
                // 名前一覧に追加
                array_push($nameList, $account->name);
            }

            // 名前一覧をカンマで連結
            $nameListStr = $name = implode(", ", $nameList);

            Main::getInstance()->sendMessageResource($player,
                ["accountSlotOver1", "accountSlotOver2", "accountSlotOver3", "accountSlotOver4"],
                ["accountSlot" => $accountSlotMax]);

            $player->sendMessage($nameListStr);

            return true;
        }

        return false;
    }

    /*
     * 名前が不適合ならtrueを返す
     */
    private function isInvalidName(Player $player) : bool
    {
        // プレイヤー名を取得
        $name = $player->getName();

        // 設定ファイルから名前の文字数の下限を取得
        $min = Main::getInstance()->getConfig()->get("nameLengthMin");

        $errorMessage = Main::getInstance()->getMessage("registerNameRule", ["min" => $min]);

        if (!preg_match("/^[a-zA-Z0-9_]+$/", $name)) {
            $player->sendMessage($errorMessage);
            return true;
        }

        // 名前の文字数を取得
        $len = strlen($name);

        // 名前が短い場合
        if ($len < $min) {
            $player->sendMessage($errorMessage);
            return true;
        }

        return false;
    }
}