<?php

namespace Jhelom\LoginAuth;

/*
 * コマンドフック
 */
class CommandHook
{
    /*
     * コンストラクタ
     */
    public function __construct(bool $isNull = false)
    {
        $this->isNull = $isNull;
    }

    // コールバック
    public $callback;

    // データ
    public $data;

    // ヌルを示すなら true
    public $isNull;
}