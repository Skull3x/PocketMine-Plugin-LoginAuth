<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;

/*
 * 同じメッセージを連続して送信しないようする
 */

class MessageThrottling
{
    // 間隔を秒単位で指定
    const INTERVAL_SECONDS = 5;

    // 最終時刻リスト
    private static $lastTimeList = [];

    // 最終メッセージリスト
    private static $lastMessageList = [];

    /*
     * メッセージを送信する
     */
    public static function send(CommandSender $sender, string $message, bool $immediate = false)
    {
        // 即時フラグが立っている場合
        if ($immediate) {
            self::clear($sender);
        }

        // キーを生成
        $key = self::makeKey($sender);

        // 現在日時を取得
        $now = new \DateTime();

        // 送信可能なら
        if (self::isSending($key, $message, $now)) {
            // 最終メッセージを更新
            self::$lastMessageList[$key] = $message;

            // 最終時刻を更新
            self::$lastTimeList[$key] = $now;

            // メッセージを送信
            foreach (explode(PHP_EOL, $message) as $str) {
                $sender->sendMessage($str);
            }
        }
    }

    /*
     * 送信可能なら true を返す
     */
    private static function isSending(string $key, string $message, \DateTime $now) : bool
    {
        // 最終メッセージにキーが不在の場合
        if (!array_key_exists($key, self::$lastMessageList)) {
            // 送信することを示す true を返す
            return true;
        }

        // 最終時刻リストにキーが不在の場合
        if (!array_key_exists($key, self::$lastTimeList)) {
            // 送信することを示す true を返す
            return true;
        }

        $lastMessage = self::$lastMessageList[$key];

        if ($lastMessage !== $message) {
            // 送信することを示す true を返す
            return true;
        }

        // 最終時刻を取得
        $lastTime = self::$lastTimeList[$key];

        // 時間の差分を取得
        $interval = $now->diff($lastTime, true);

        // 時差が指定値以上の場合
        if ($interval->s >= self::INTERVAL_SECONDS) {
            // 送信することを示す true を返す
            return true;
        }

        // 送信しないことを示す false を返す
        return false;
    }

    private
    static function makeKey(CommandSender $sender) : string
    {
        return $sender->getName();
    }

    public
    static function clear(CommandSender $sender)
    {
        $key = self::makeKey($sender);
        unset(self::$lastTimeList[$key]);
        unset(self::$lastMessageList[$key]);
    }
}