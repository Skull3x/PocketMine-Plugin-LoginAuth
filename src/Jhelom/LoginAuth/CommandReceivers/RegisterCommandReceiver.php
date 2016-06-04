<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

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
            $sender->sendMessage(TextFormat::YELLOW . Main::getInstance()->getMessage("registerConfirm"));
            $invoker->getHookQueue()->enqueue([$this, "execute2"], $sender, $password);
        }
    }

    public function execute2(CommandInvoker $invoker, CommandSender $sender, array $args, $data)
    {
        $password = array_shift($args) ?? "";

        Main::getInstance()->getLogger()->debug("register: execute2: " . $password . " = " . $data);

        if ($data !== $password) {
            $sender->sendMessage(TextFormat::YELLOW . Main::getInstance()->getMessage(("registerConfirmError")));
            return;
        }

        if ($this->register($sender, $password)) {
            $sender->sendMessage(TextFormat::GREEN . Main::getInstance()->getMessage("registerSuccessful"));
        }
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
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("loginAlready"));
            return false;
        }

        // アカウントを検索
        $account = Main::getInstance()->findAccountByName($player->getName());

        // 同じ名前のアカウントが存在する場合
        if (!$account->isNull) {
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("registerExists", ["name" => $player->getName()]));
            return false;
        }

        // パスワードを検証
        if (!Main::getInstance()->validatePassword($player, $password, Main::getInstance()->getMessage("passwordRequired"))) {
            return false;
        }

        // 端末IDをもとにデータベースからアカウント一覧を取得
        $accountList = Main::getInstance()->findAccountsByClientId($player->getClientId());

        // アカウント一覧の数
        $accountListCount = count($accountList);

        // 端末毎のアカウント上限数を取得（最低１以上で補正）
        $accountSlot = min(1, Main::getInstance()->getConfig()->get("accountSlot"));

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

            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("accountSlotOver1", ["accountSlot" => $accountSlot]));
            $sender->sendMessage(TextFormat::RED . Main::getInstance()->getMessage("accountSlotOver2"));
            $sender->sendMessage(TextFormat::RED . $nameListStr);

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
        $stmt->bindValue(":passwordHash", Main::getInstance()->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", Main::getInstance()->getSecurityStampManager()->makeStamp($player), \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        Main::getInstance()->getSecurityStampManager()->add($player);

        $sender->sendMessage(TextFormat::GREEN . Main::getInstance()->getMessage("registerSuccessful"));

        return true;
    }
}