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

    public $player;

    public $callback;

    public $data;

    public $isNull;
}