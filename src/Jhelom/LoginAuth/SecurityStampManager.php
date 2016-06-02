<?php

namespace Jhelom\LoginAuth;


use pocketmine\Player;

/**
 * セキュリティスタンプマネージャー
 *
 * ログイン認証の状態を管理する
 *
 * @package LoginAuth
 */
class SecurityStampManager
{
    // キャッシュ
    private $cache = [];

    /**
     * キーを生成する
     *
     * @param Player $player
     * @return string
     */
    private function makeKey(Player $player) : string
    {
        return $player->getRawUniqueId();
    }

    /**
     * セキュリティスタンプを生成する
     *
     * @param Player $player
     * @return string
     */
    public function makeStamp(Player $player) : string
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
        return hash("sha256", $seed);
    }

    /**
     * プレイヤーがログイン認証済みかどうかセキュリティスタンプを検証する
     * 認証済みなら true を返す
     *
     * @param Player $player
     * @return bool
     */
    public function validate(Player $player) : bool
    {
        // キャッシュのスタンプを取得
        $stamp1 = $this->get($player);

        // プレイヤーのスタンプを取得
        $stamp2 = $this->makeStamp($player);

        // 比較結果を返す
        return $stamp1 === $stamp2;
    }

    /**
     * キャッシュからセキュリティスタンプを取得する
     *
     * @param Player $player
     * @return string
     */
    public function get(Player $player) : string
    {
        // キーを生成
        $key = $this->makeKey($player);

        // キャッシュにキーが存在するなら
        if (array_key_exists($key, $this->cache)) {
            // キャッシュからスタンプを取得して返す
            return $this->cache[$key];
        }

        // キーが不在の場合は空文字を返す
        return "";
    }

    /**
     * キャッシュに追加する
     *
     * @param Player $player
     */
    public function add(Player $player)
    {
        // キーを生成
        $key = $this->makeKey($player);

        // スタンプを生成
        $stamp = $this->makeStamp($player);

        // キャッシュに登録
        $this->cache[$key] = $stamp;
    }

    /**
     * キャッシュから削除する
     *
     * @param Player $player
     */
    public function remove(Player $player)
    {
        // キーを生成
        $key = $this->makeKey($player);

        // キャッシュから削除
        unset($this->cache[$key]);
    }
}

?>
