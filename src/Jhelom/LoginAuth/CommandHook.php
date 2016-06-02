<?php

namespace Jhelom\LoginAuth;


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

    public $sender;

    // コールバック
    public $callback;

    // データ
    public $data;

    public $isNull;
}