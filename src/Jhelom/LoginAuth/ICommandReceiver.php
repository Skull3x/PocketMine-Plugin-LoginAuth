<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;

/*
 * コマンドレシーバー
 * 
 * コマンドを処理するインターフェース
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
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args);

}