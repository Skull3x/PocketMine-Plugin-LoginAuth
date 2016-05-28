<?php

namespace LoginAuth;


class Account
{
    // プレイヤー名
    public $name;

    // クライアントID
    public $clientId;

    // IPアドレス
    public $ip;

    // パスワードハッシュ
    public $passwordHash;

    // セキュリティスタンプ
    public $securityStamp;
}