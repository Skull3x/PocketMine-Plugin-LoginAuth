<?php

namespace LoginAuth;


class Account
{
    public $name;

    // 名前
    public $clientId;

    // 端末トID
    public $ip;

    // IPアドレス
    public $passwordHash;

    // パスワードハッシュ
    public $securityStamp;

    // セキュリティスタンプ
    public $isNull;

    // オブジェクトが無効である（データベースに存在しない）ことを示す

    /**
     * コンストラクタ
     *
     * @param bool $isNull
     */
    function __construct(bool $isNull = false)
    {
        $this->isNull = $isNull;
    }
}
