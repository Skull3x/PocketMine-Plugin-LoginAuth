<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;

/*
 * コマンドレシーバーのインターフェース
 */

interface ICommandReceiver
{
    /*
     * コマンドの名前
     */
    public function getName() : string;

    /*
     * コンソール実行許可
     */
    public function isAllowConsole() : bool;

    /*
     * プレイヤー実行許可
     */
    public function isAllowPlayer() : bool;

    /*
     * OPのみ実行許可
     */
    public function isAllowOpOnly(): bool;

    /*
     * ログイン認証済みの場合のみ実行許可
     */
    public function isAllowAuthenticated() : bool;

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args);

}