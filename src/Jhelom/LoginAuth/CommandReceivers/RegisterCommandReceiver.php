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

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        $password = array_shift($args) ?? "";

        if ($this->tryRegister($sender, $password)) {
            Main::getInstance()->sendMessageResource($sender, "registerConfirm");
            $invoker->getHookQueue()->enqueue([$this, "execute2"], $sender, $password);
        }
    }

    public function execute2(CommandInvoker $invoker, CommandSender $sender, array $args, $data)
    {
        Main::getInstance()->getLogger()->debug("register: execute2: ");

        $password = array_shift($args) ?? "";

        if ($data !== $password) {
            Main::getInstance()->sendMessageResource($sender, "registerConfirmError");
            return;
        }

        $this->register($sender, $password);
    }

    /*
    * アカウント登録が可能か検証して、成功なら true を返す
    * （データベースへの反映は行わない）
    */
    public function tryRegister(CommandSender $sender, string $password) : bool
    {
        // Playerクラスにキャスト
        $player = Main::getInstance()->castCommandSenderToPlayer($sender);

        // 既にログイン認証済みの場合
        if (Main::getInstance()->isAuthenticated($player)) {
            Main::getInstance()->sendMessageResource($sender, "loginAlready");
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // 同じ名前のアカウントが存在する場合
        if (!$account->isNull) {
            Main::getInstance()->sendMessageResource($sender, "registerExists", ["name" => $player->getName()]);
            return false;
        }

        // 名前が不適合の場合
        if ($this->isInvalidName($player)) {
            return false;
        }

        // パスワードが不適合の場合
        if ($this->isInvalidPassword($player, $password)) {
            return false;
        }

        // 端末毎アカウント数が不適合の場合
        if ($this->isInvalidAccountSlot($player)) {
            return false;
        }

        return true;
    }

    /*
     * アカウントをデータベースに登録する
     *
     * 成功なら true を返す
     */
    public function register(CommandSender $sender, string $password) :bool
    {
        if (!$this->tryRegister($sender, $password)) {
            return false;
        }

        // Playerクラスにキャスト
        $player = Main::getInstance()->castCommandSenderToPlayer($sender);

        //　データベースに登録
        $sql = "INSERT INTO account (name, clientId, ip, passwordHash, securityStamp) VALUES (:name, :clientId, :ip, :passwordHash, :securityStamp)";
        $stmt = Main::getInstance()->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", Account::makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", Account::makeSecurityStamp($player), \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        Main::getInstance()->getLoginCache()->add($player);

        Main::getInstance()->sendMessageResource($player, "registerSuccessful", ["password" => $password]);

        return true;
    }

    /*
     * 端末毎アカウント登録数が不適合ならtrueを返す
     */
    private function isInvalidAccountSlot(Player $player) : bool
    {
        // 端末IDをもとにデータベースからアカウント一覧を取得
        $accountList = Main::getInstance()->findAccountsByClientId($player->getClientId());

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
                ["accountSlotOver1", "accountSlotOver2"],
                ["accountSlot" => $accountSlotMax]);

            $player->sendMessage($nameListStr);

            return true;
        }

        return false;
    }

    /*
     * パスワードが不適合ならtrueを返す
    */
    private function isInvalidPassword(CommandSender $sender, string $password) : bool
    {
        // パスワードが空欄の場合
        if ($password === "") {
            Main::getInstance()->sendMessageResource($sender, ["passwordRequired", "registerUsage"]);
            return true;
        }

        // 使用可能文字の検証
        if (!preg_match("/^[a-zA-Z0-9!#@]+$/", $password)) {
            Main::getInstance()->sendMessageResource($sender, "passwordFormat");
            return true;
        }

        // 設定ファイルからパスワードの文字数の下限を取得
        $passwordLengthMin = Main::getInstance()->getConfig()->get("passwordLengthMin");

        // 設定ファイルからパスワードの文字数の上限を取得
        $passwordLengthMax = Main::getInstance()->getConfig()->get("passwordLengthMax");

        // パスワードの文字数を取得
        $passwordLength = strlen($password);

        // パスワードが短い場合
        if ($passwordLength < $passwordLengthMin) {
            Main::getInstance()->sendMessageResource($sender, "passwordLengthMin", ["length" => $passwordLengthMin]);
            return true;
        }

        // パスワードが長い場合
        if ($passwordLength > $passwordLengthMax) {
            Main::getInstance()->sendMessageResource($sender, "passwordLengthMax", ["length" => $passwordLengthMax]);
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

        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
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