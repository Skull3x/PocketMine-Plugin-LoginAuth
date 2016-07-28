<?php

namespace Jhelom\LoginAuth;

use pocketmine\item\Minecart;
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
    public $lastLoginTime;

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

    public function saveToJson()
    {
        $dir = Main::getInstance()->getDataFolder() . "accounts";

        if(!is_dir($dir))
        {
            mkdir($dir, true);
        }

        $path =  $dir . DIRECTORY_SEPARATOR . $this->name . ".json";
        $data = new AccountData();
        $data->name = $this->name;
        $data->ip = $this->ip ?? "";
        $data->clientId = $this->clientId ?? "";
        $data->passwordHash = $this->passwordHash ?? "";
        $data->securityStamp = $this->securityStamp ?? "";
        $data->lastLoginTime = $this->lastLoginTime ?? "";
        $json = json_encode($data, JSON_PRETTY_PRINT);

        //Main::getInstance()->getLogger()->info($path . PHP_EOL . $json);
        file_put_contents($path, $json);
    }
}
