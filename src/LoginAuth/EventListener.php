<?php

namespace LoginAuth;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

require_once("CommandHook.php");
require_once("CommandHookQueue.php");

class EventListener implements Listener
{
    // メッセージ表示間隔を秒単位で指定
    const SHOW_MESSAGE_INTERVAL_SECONDS = 5;

    // メイン
    private $main;

    // コマンドフックキュー
    private $commandHookQueue;

    // メッセージを一定時間経過後に表示するための管理リスト
    private $sendAuthMessageTime = [];

    // コマンドテーブル
    // キーにコマンドの文字列を、バリューにメソッド名を記述
    private static $commandTable = [
        "register" => "dispatchRegister",
        "unregister" => "dispatchUnregister",
        "login" => "dispatchLogin",
        "password" => "dispatchChangePassword",
    ];

    /**
     * コンストラクタ
     * @param Main $main
     */
    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->commandHookQueue = new CommandHookQueue();
    }

    /**
     * @param PlayerPreLoginEvent $event
     */
    public function onPlayerPreLogin(PlayerPreLoginEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerPreLogin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 名前を小文字に変換
        $name = strtolower($player->getName());

        // 重複ログインを禁止するために、既に別端末からログインしていたら拒否する

        // ログイン中の全プレイヤーの一覧を取得
        $onlinePlayerList = $this->main->getServer()->getOnlinePlayers();

        // ログイン中の全プレイヤーをループ
        foreach ($onlinePlayerList as $onlinePlayer) {
            // ログイン中のプレイヤーの名前を小文字に変換
            $onlinePlayerName = strtolower($onlinePlayer->getName());

            // 名前が同じで
            if ($onlinePlayer !== $player and $onlinePlayerName === $name) {
                // ログイン認証済みなら
                if ($this->main->isAuthenticated($onlinePlayer)) {
                    // イベントをキャンセル
                    $event->setCancelled(true);

                    // 拒否する
                    $event->setKickMessage("既に別端末からログインしています。先にログインしている端末からログアウトしてやり直してください。");
                    return;
                }
            }
        }
    }

    /**
     * プレイヤーがリスポーンしたときのイベント
     * @param PlayerRespawnEvent $event
     */
    public function onPlayerRespawn(PlayerRespawnEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerRespawn: ");

        $player = $event->getPlayer();
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerJoin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みなら
        if ($this->main->isAuthenticated($player)) {
            // ログイン認証済みメッセージ表示
            $player->sendMessage(TextFormat::GREEN . $this->main->getMessage("alreadyLogin"));
        } else {
            $this->sendAuthMessage($player, true);
        }
    }

    /**
     * プレイヤーがログアウトしたときのイベント
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerQuit: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // コマンドフックをクリア
        $this->commandHookQueue->clear($player);

        // メッセージ表示時刻を削除
        $key = $player->getRawUniqueId();
        unset($this->sendAuthMessageTime[$key]);
    }

    /**
     * プレイヤーがコマンドを実行したときのイベント
     *
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerCommand: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // プレイヤーが入力したメッセージを取得
        $message = $event->getMessage();

        $hook = $this->commandHookQueue->dequeue($player);

        if (!$hook->isNull) {
            call_user_func($hook->callback, $player, explode(" ", $message), $hook);
            $event->setCancelled(true);
            return;
        }

        //  メッセージの先頭文字が /（スラッシュ）でなければ（つまりコマンド書式ではない）
        if (strpos($message, "/") !== 0) {
            // 何もしないでリターン
            return;
        }

        // メッセージから先頭スラッシュを除去してから空白文字で分割
        $args = explode(" ", substr($message, 1));

        // コマンドを処理
        if ($this->dispatch(self::$commandTable, $player, $args)) {
            // 処理が成功ならイベントをキャンセル
            $event->setCancelled(true);
        }
    }

    /**
     * コマンドを処理する、正常に処理が完了した場合 true を返す
     *
     * @param array $itemList
     * @param Player $player
     * @param array $args
     * @return bool
     */
    private function dispatch(array $itemList, Player $player, array $args):bool
    {
        // 配列の先頭の文字列を取得して、英小文字に変換
        $command = strtolower(array_shift($args) ?? "");

        // キーが存在すれば
        if (array_key_exists($command, $itemList)) {
            $item = $itemList[$command];
            call_user_func([$this, $item], $player, $args);
            return true;
        } else {
            return false;
        }
    }

    /**
     * プレイヤーが移動したときのイベント
     *
     * @param PlayerMoveEvent $event
     */
    public function onPlayerMove(PlayerMoveEvent $event)
    {
        // $this->main->getLogger()->debug("onPlayerMove: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みの場合
        if ($this->main->isAuthenticated($player)) {
            // 何もしないでリターン
            return;
        }

        // イベントをキャンセル
        $event->setCancelled(true);
        $event->getPlayer()->onGround = true;
        $this->sendAuthMessage($player);
    }

    /**
     * プレイヤーがインタラクションしたときのイベント
     *
     * @param PlayerInteractEvent $event
     */
    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerInteract: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みの場合
        if ($this->main->isAuthenticated($player)) {
            // 何もしないでリターン
            return;
        }

        // イベントをキャンセル
        $event->setCancelled(true);
        $this->sendAuthMessage($player);
    }

    /**
     * プレイヤーがアイテムを置いたときのイベント
     *
     * @param PlayerDropItemEvent $event
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerPreLogin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みの場合
        if ($this->main->isAuthenticated($player)) {
            // 何もしないでリターン
            return;
        }

        // イベントをキャンセル
        $event->setCancelled(true);
        $this->sendAuthMessage($player);
    }

    /**
     * プレイヤーがアイテムを装備したときのイベント
     *
     * @param PlayerItemConsumeEvent $event
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerItemConsume: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みの場合
        if ($this->main->isAuthenticated($player)) {
            // 何もしないでリターン
            return;
        }

        // イベントをキャンセル
        $event->setCancelled(true);
        $this->sendAuthMessage($player);
    }

    /**
     * ダメージを受けたときのイベント
     *
     * @param EntityDamageEvent $event
     */
    public function onEntityDamage(EntityDamageEvent $event)
    {
        $this->main->getLogger()->debug("onEntityDamage: ");

        $entity = $event->getEntity();

        // エンティティが Playerクラスで
        if ($entity instanceof Player) {
            // 認証済みの場合
            if ($this->main->isAuthenticated($entity)) {
                // 何もしないでリターン
                return;
            }

            // イベントをキャンセル
            $event->setCancelled(true);
            $this->sendAuthMessage($entity);
            return;
        }
    }

    /**
     * ブロックを破壊したときのイベント
     *
     * @param BlockBreakEvent $event
     */
    public function onBlockBreak(BlockBreakEvent $event)
    {
        $this->main->getLogger()->debug("onBlockBreak: ");

        $player = $event->getPlayer();

        if ($player instanceof Player) {
            // 認証済みの場合
            if ($this->main->isAuthenticated($player)) {
                // 何もしないでリターン
                return;
            }

            // イベントをキャンセル
            $event->setCancelled(true);
            $this->sendAuthMessage($player);
            return;
        }
    }

    /**
     * ブロックを設置したときのイベント
     *
     * @param BlockPlaceEvent $event
     */
    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $this->main->getLogger()->debug("onBlockPlace: ");

        $player = $event->getPlayer();

        if ($player instanceof Player) {
            // 認証済みの場合
            if ($this->main->isAuthenticated($player)) {
                // 何もしないでリターン
                return;
            }

            // イベントをキャンセル
            $event->setCancelled(true);
            $this->sendAuthMessage($player);
            return;
        }
    }

    /**
     * インベントリを開くときのイベント
     *
     * @param InventoryOpenEvent $event
     */
    public function onInventoryOpen(InventoryOpenEvent $event)
    {
        $this->main->getLogger()->debug("onInventoryOpen: ");

        $player = $event->getPlayer();

        if ($player instanceof Player) {
            // 認証済みの場合
            if ($this->main->isAuthenticated($player)) {
                // 何もしないでリターン
                return;
            }

            // イベントをキャンセル
            $event->setCancelled(true);
            $this->sendAuthMessage($player);
            return;
        }
    }

    /**
     * アイテムを拾ったときのイベント
     *
     * @param InventoryPickupItemEvent $event
     */
    public function onPickupItem(InventoryPickupItemEvent $event)
    {
        $this->main->getLogger()->debug("onPickupItem: ");

        $player = $event->getInventory()->getHolder();

        if ($player instanceof Player) {
            // 認証済みの場合
            if ($this->main->isAuthenticated($player)) {
                // 何もしないでリターン
                return;
            }

            // イベントをキャンセル
            $event->setCancelled(true);
        }
    }

    private function dispatchRegister(Player $player, array $args)
    {
        $this->main->getLogger()->debug("dispatchRegister: ");

        $password = array_shift($args) ?? "";

        if ($this->main->tryRegister($player, $password)) {
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("registerConfirm"));
            $this->commandHookQueue->enqueue([$this, "dispatchRegisterConfirm"], $player, $password);
        }
    }

    private function dispatchRegisterConfirm(Player $player, array $args, CommandHook $hook)
    {
        $this->main->getLogger()->debug("dispatchRegisterConfirm: ");

        $password = array_shift($args) ?? "";

        if ($hook->data !== $password) {
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("registerConfirmError"));
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("registerUsage"));
            return;
        }

        $this->main->register($player, $password);
    }


    /**
     * @param Player $player
     * @param array $args
     * @return bool
     */
    private function dispatchUnregister(Player $player, array $args)
    {
        $this->main->getLogger()->debug("dispatchUnregister: ");

        $password = array_shift($args) ?? "";

        if ($this->main->tryUnregister($player, $password)) {
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("unregisterConfirm"));
            $this->commandHookQueue->enqueue([$this, "dispatchUnregisterConfirm"], $player, $password);

        }
    }

    private function dispatchUnregisterConfirm(Player $player, array $args, CommandHook $hook)
    {
        $input = strtolower(array_shift($args) ?? "");

        if ($input !== "y") {
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("unregisterCancel"));
            return;
        }

        $this->main->unregister($player, $hook->data);
    }

    /**
     * @param Player $player
     * @param array $args
     * @return bool
     */
    private function dispatchLogin(Player $player, array $args)
    {
        $this->main->getLogger()->debug("dispatchLogin: ");

        $password = array_shift($args) ?? "";
        $this->main->login($player, $password);
    }


    /**
     * @param Player $player
     * @param array $args
     * @return bool
     */
    private function dispatchChangePassword(Player $player, array $args)
    {
        $this->main->getLogger()->debug("dispatchChangePassword: ");

        $newPassword = array_shift($args) ?? "";

        if ($this->main->tryChangePassword($player, $newPassword)) {
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("passwordConfirm"));
            $this->commandHookQueue->enqueue([$this, "dispatchChangePasswordConfirm"], $player, $newPassword);
        }
    }

    private function dispatchChangePasswordConfirm(Player $player, array $args, CommandHook $hook)
    {
        $this->main->getLogger()->debug("dispatchChangePasswordConfirm: ");

        $newPassword = array_shift($args) ?? "";

        if ($newPassword !== $hook->data) {
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("passwordError"));
            return;
        }

        $this->main->changePassword($player, $newPassword);
    }

    /**
     * @param Player $player
     * @param bool $force
     */
    private function sendAuthMessage(Player $player, bool $force = false)
    {
        // キーを生成
        $key = $player->getRawUniqueId();

        // 現在日時を取得
        $now = new \DateTime();

        // 強制フラグが立っている場合
        if ($force) {
            unset($this->sendAuthMessageTime[$key]);
        }

        // キーが存在する場合
        if (array_key_exists($key, $this->sendAuthMessageTime)) {
            // 最終表示時刻を取得
            $lastTime = $this->sendAuthMessageTime[$key];

            // 時間の差分を取得
            $interval = $now->diff($lastTime);

            // 時差が指定値以下なら
            if ($interval->s <= self::SHOW_MESSAGE_INTERVAL_SECONDS) {
                // リターン
                return;
            }
        }

        // 最終表示時刻を更新
        $this->sendAuthMessageTime[$key] = $now;

        // アカウント登録状態に応じて表示するメッセージを切り替える
        if ($this->main->isRegistered($player)) {
            // ログインしてもらうメッセージ
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("login"));
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("loginUsage"));
        } else {
            // 未登録ならアカウント登録してもらうメッセージ
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("register"));
            $player->sendMessage(TextFormat::RED . $this->main->getMessage("registerUsage"));
        }
    }
}
