<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

class AuthCommandReceiver implements ICommandReceiver
{
    // サブコマンド用のインボーカー
    private $subInvoker;

    /*
     * コンストラクタ
     */
    public function __construct()
    {
        $this->subInvoker = new CommandInvoker();

        // サブコマンドを登録
        $this->subInvoker->add(new UnregisterCommandReceiver());
        $this->subInvoker->add(new AddCommandReceiver());
        $this->subInvoker->add(new FindCommandReceiver());
        $this->subInvoker->add(new UnlockCommandReceiver());
    }

    /*
     * コマンドの名前
     */
    public function getName() : string
    {
        return "auth";
    }

    /*
     * コンソール実行許可
     */
    public function isAllowConsole() : bool
    {
        return true;
    }

    /*
     * プレイヤー実行許可
     */
    public function isAllowPlayer() : bool
    {
        return false;
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
    public function execute(CommandSender $sender, array $args)
    {
        $cmd = array_shift($args) ?? "";

        if (!$this->subInvoker->execute($sender, $cmd, $args)) {
            Main::getInstance()->sendMessageResource($sender,
                [
                    "authHelp",
                    "authUsage2",
                    "authUsage3",
                    "authUsage4",
                    "authUsage5",
                ]);
        }
    }
}