<?php

namespace Jhelom\LoginAuth;

use pocketmine\Player;

/*
 * アカウント
 */
class Account
{
    const HASH_ALGORITHM = "sha256";

    /*
     * コンストラクタ
     */
    public function __construct(bool $isNull = false)
    {
        $this->isNull = $isNull;
    }

    // 名前
    public $name;

    // 端末ID
    public $clientId;

    // IPアドレス
    public $ip;

    // パスワードハッシュ
    public $passwordHash;

    // セキュリティスタンプ
    public $securityStamp;

    // 最終ログにン日時（文字列）
    public $lastLogin;

    // データベース不在なら true を示す
    public $isNull;

    /*
     * セキュリティスタンプを生成する
     */
    public static function makeSecurityStamp(Player $player) : string
    {
        // 名前
        $name = strtolower($player->getName());

        // 端末ID
        $clientId = $player->getClientId();

        // IPアドレス
        $ip = $player->getAddress();

        // 連結
        $seed = $name . $clientId . $ip;

        // ハッシュ
        return hash(self::HASH_ALGORITHM, $seed);
    }

    /*
     * パスワードハッシュを生成する
     */
    public static function makePasswordHash(string $password) : string
    {
        return hash(self::HASH_ALGORITHM, $password);
    }
}
