<?php

namespace Jhelom\LoginAuth;


use pocketmine\command\CommandSender;
use pocketmine\Player;

/*
 * コマンドフックのリストを管理する
 */

class CommandHookManager
{
    private $list = [];

    /*
     * コンストラクタ
     * シングルトンにするために private にして new できないようにする
     */
    private function __construct()
    {
    }

    // シングルトンのインスタンスを保持
    private static $instance;

    /*
     * シングルトンのインスタンスを取得
     */
    public static function getInstance() : CommandHookManager
    {
        // インスタンスが初期化されていない場合
        // PHP はシングルスレッドなので排他制御は不要
        if (self::$instance === NULL) {
            // インスタンスを初期化
            self::$instance = new CommandHookManager();
        }

        return self::$instance;
    }

    /*
     * クローンを禁止にする
     */
    final function __clone()
    {
        throw new \Exception("クローン禁止 " . get_class($this));
    }

    /*
     * キーを生成
     */
    public function makeKey(CommandSender $sender) : string
    {
        // Player の場合
        if ($sender instanceof Player) {
            // キャストして
            $player = Main::castToPlayer($sender);

            // ユニークIDを返す
            return $player->getRawUniqueId();
        } else {
            // Player ではない場合、名前を返す
            return $sender->getName();
        }
    }

    /*
     * キューが存在すれば true を返す
     */
    public function exists(CommandSender $player) : bool
    {
        $key = $this->makeKey($player);

        if (!array_key_exists($key, $this->list)) {
            return false;
        }

        if (count($this->list[$key]) === 0) {
            return false;
        }

        return true;
    }

    /*
     * プレイヤーに関連付けされたキーを取り出す。不在の場合は isNull が true の CommandHook を返す
     */
    public function dequeue(CommandSender $sender) : CommandHook
    {
        $key = $this->makeKey($sender);

        if (!array_key_exists($key, $this->list)) {
            return new CommandHook(true);
        }

        $hook = array_shift($this->list[$key]);

        if ($hook === NULL) {
            return new CommandHook(true);
        }

        return $hook;
    }

    /*
     * キューに入れる
     */
    public function enqueue(array $callback, CommandSender $sender, $data = NULL)
    {
        $key = $this->makeKey($sender);

        if (!array_key_exists($key, $this->list)) {
            $this->list[$key] = [];
        }

        $hook = new CommandHook();
        $hook->callback = $callback;
        $hook->data = $data;

        array_push($this->list[$key], $hook);
    }

    /*
     *  キューをクリアする
     */
    public function clear(CommandSender $sender)
    {
        $key = $this->makeKey($sender);

        unset($this->list[$key]);
    }
}

?>