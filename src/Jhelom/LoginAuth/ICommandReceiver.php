<?php
/**
 * Created by PhpStorm.
 * User: yoshizawa
 * Date: 2016/06/02
 * Time: 14:17
 */

namespace Jhelom\LoginAuth;

use pocketmine\command\Command;
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
     * コンソールから実行可能なら true を返す
     */
    public function isAllowConsole() : bool;

    /*
     * プレイヤーが実行可能なら true を返す
     */
    public function isAllowPlayer() : bool;

    /*
     * 実行する
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, Command $command, array $args);

}