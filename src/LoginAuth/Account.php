<?php

namespace LoginAuth;


class Account
{
    public $name;

    // 名前
    public $clientId;

    // 端末ID
    public $ip;

    // IPアドレス
    public $passwordHash;

    // パスワードハッシュ
    public $securityStamp;

    // セキュリティスタンプ
    public $isNull;

    // オブジェクトが無効（データベースに存在しない）なら true

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
