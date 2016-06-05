<?php

namespace Jhelom\LoginAuth;


use pocketmine\Player;

/*
 * ログイン認証の状態をキャッシュする
 */

class LoginCache
{
    // キャッシュ
    private $list = [];

    /*
     * キーを生成する
     */
    private function makeKey(Player $player) : string
    {
        return $player->getRawUniqueId();
    }

    /*
     * プレイヤーがログイン認証済みかどうかセキュリティスタンプを検証する
     * 認証済みなら true を返す
     */
    public function validate(Player $player) : bool
    {
        // キャッシュのスタンプを取得
        $stamp1 = $this->get($player);

        // プレイヤーのスタンプを取得
        $stamp2 = Account::makeSecurityStamp($player);

        // 比較結果を返す
        return $stamp1 === $stamp2;
    }

    /*
     * キャッシュからセキュリティスタンプを取得する
     */
    public function get(Player $player) : string
    {
        // キーを生成
        $key = $this->makeKey($player);

        // キャッシュにキーが存在するなら
        if (array_key_exists($key, $this->list)) {
            // キャッシュからスタンプを取得して返す
            return $this->list[$key];
        }

        // キーが不在の場合は空文字を返す
        return "";
    }

    /*
     * キャッシュに追加する
     */
    public function add(Player $player)
    {
        // キーを生成
        $key = $this->makeKey($player);

        // スタンプを生成
        $stamp = Account::makeSecurityStamp($player);

        // キャッシュに登録
        $this->list[$key] = $stamp;
    }

    /*
     * キャッシュから削除する
     */
    public function remove(Player $player)
    {
        // キーを生成
        $key = $this->makeKey($player);

        // キャッシュから削除
        unset($this->list[$key]);
    }
}

?>
