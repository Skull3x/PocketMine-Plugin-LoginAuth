<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\MessageThrottling;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class RegisterCommandReceiver implements ICommandReceiver
{
    public function getName() : string
    {
        return "register";
    }

    public function isAllowConsole() : bool
    {
        return false;
    }

    public function isAllowPlayer() : bool
    {
        return true;
    }

    public function execute(CommandInvoker $invoker, CommandSender $sender, Command $command, array $args)
    {
        $password = array_shift($args) ?? "";

        if ($this->tryRegister($sender, $password)) {
            $invoker->getHookQueue()->enqueue([$this, "execute2"], $sender, $password);
        }
    }

    public function execute2()
    {
        $this->register();
    }

    /*
    * アカウント登録が可能か検証して、成功なら true を返す
    * （データベースへの反映は行わない）
    */
    public function tryRegister(CommandSender $sender, string $password) : bool
    {
        // Playerクラスにキャスト
        $player = $this->castToPlayer($sender);

        // 既にログイン認証済みの場合
        if ($this->isAuthenticated($player)) {
            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("loginAlready"));
            return false;
        }

        // アカウントを検索
        $account = $this->findAccountByName($player->getName());

        // 同じ名前のアカウントが存在する場合
        if (!$account->isNull) {
            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("registerExists", ["name" => $player->getName()]));
            return false;
        }

        // パスワードを検証
        if (!$this->validatePassword($player, $password, $this->getMessage("passwordRequired"))) {
            return false;
        }

        // 端末IDをもとにデータベースからアカウント一覧を取得
        $accountList = $this->findAccountsByClientId($player->getClientId());

        // アカウント一覧の数
        $accountListCount = count($accountList);

        // 端末毎のアカウント上限数を取得（最低１以上で補正）
        $accountSlot = min(1, $this->getConfig()->get("accountSlot"));

        // アカウント上限数を超過している場合
        if ($accountSlot < $accountListCount) {
            // 名前一覧を組み立てるための配列
            $nameList = [];

            // アカウント一覧をループ
            foreach ($accountList as $account) {
                // 名前一覧に追加
                array_push($nameList, $account->name);
            }

            // 名前一覧をカンマで連結
            $nameListStr = $name = implode(",", $nameList);

            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("accountSlotOver1", ["accountSlot" => $accountSlot]));
            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("accountSlotOver2"));
            MessageThrottling::send($player, TextFormat::RED . $nameListStr);

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
        $player = $this->castToPlayer($sender);

        //　データベースに登録
        $sql = "INSERT INTO account (name, clientId, ip, passwordHash, securityStamp) VALUES (:name, :clientId, :ip, :passwordHash, :securityStamp)";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $this->getSecurityStampManager()->makeStamp($player), \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        $this->getSecurityStampManager()->add($player);

        MessageThrottling::send($player, TextFormat::GREEN . $this->getMessage("registerSuccessful"));

        return true;
    }
}