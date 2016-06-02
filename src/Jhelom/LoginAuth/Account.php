<?php

namespace Jhelom\LoginAuth;

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

    public $isNull;
}
