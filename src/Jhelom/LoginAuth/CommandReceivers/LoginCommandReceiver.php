<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use Jhelom\LoginAuth\MessageThrottling;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class LoginCommandReceiver implements ICommandReceiver
{
    private $main;

    /*
     * コンストラクタ
     */
    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    public function getName() : string
    {
        return "login";
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
        // TODO: Implement execute() method.
    }


    /*
     * ログインする
     */
    public function login(CommandSender $sender, string $password):bool
    {
        // コマンド実行者がプレイヤーでない場合
        if ($this->isNotPlayer($sender)) {
            MessageThrottling::send($sender, TextFormat::RED . $this->getMessage("commandAtClient"));
            return false;
        }

        // Playerクラスにキャスト
        $player = $this->castToPlayer($sender);

        // 既にログイン認証済みの場合
        if ($this->isAuthenticated($player)) {
            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("loginAlready"));
            return false;
        }

        // アカウントを検索
        $account = $this->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("register"));
            return false;
        }

        // 空白文字を除去
        $password = trim($password);

        // パスワードを検証
        if (!$this->validatePassword($player, $password, $this->getMessage("passwordRequired"))) {
            return false;
        }

        // パスワードハッシュを生成
        $passwordHash = $this->makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            // パスワード不一致メッセージを表示してリターン
            MessageThrottling::send($player, TextFormat::RED . $this->getMessage("passwordError"));
            return false;
        }

        // データベースのセキュリティスタンプを更新
        $securityStamp = $this->getSecurityStampManager()->makeStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        $this->getSecurityStampManager()->add($player);

        // ログイン成功メッセージを表示
        MessageThrottling::send($player, TextFormat::GREEN . $this->getMessage("loginSuccessful"));

        return true;
    }


}