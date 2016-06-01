<?php

namespace LoginAuth;

class CommandHook
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

    // プレイヤー
    public $player;

    // コールバック
    public $callback;

    // データ
    public $data;

    public $isNull;
}