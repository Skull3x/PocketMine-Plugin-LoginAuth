<?php

namespace LoginAuth;


class Account
{
    /**
     * コンストラクタ
     * 
     * @param bool $isNull
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

    // 削除済みなら 1
    public $isDeleted;

    public $isNull;
}
