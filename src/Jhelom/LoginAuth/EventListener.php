<?php

namespace Jhelom\LoginAuth;

use Jhelom\LoginAuth\CommandReceivers\AuthCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\LoginCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\RegisterCommandReceiver;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Cancellable;
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
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\inventory\InventoryHolder;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

/*
 * イベントリスナー
 */

class EventListener implements Listener
{
    const INTERVAL_SECONDS = 5;

    // メイン
    private $main;

    private $lastSendMessageTime = [];

    private $invoker;

    /*
     * コンストラクタ
     */
    public function __construct(Main $main)
    {
        $this->main = $main;

        // インボーカーを初期化
        $this->invoker = new CommandInvoker($main);
        $this->invoker->add(new RegisterCommandReceiver());
        $this->invoker->add(new LoginCommandReceiver());
        $this->invoker->add(new AuthCommandReceiver());
    }

    /*
     * プレイヤーがコマンドを実行するときのイベント
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerCommand: " . $event->getPlayer()->getName());

        $this->invoker->invokePlayerCommand($event);
    }

    /*
     * コンソールからコマンドを実行するときのイベント
     */
    public function onServerCommand(ServerCommandEvent $event)
    {
        $this->main->getLogger()->debug("onServerCommand: " . $event->getSender()->getName());

        $this->invoker->invokeServerCommand($event);
    }

    /*
     * プレイヤーがログインするときのイベント
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
                    $event->setKickMessage($this->main->getMessage("loginMultiDeviceError"));
                    return;
                }
            }
        }
    }

    /*
     * プレイヤーがゲームに参加するときのイベント
     */
    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerJoin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みなら
        if ($this->main->isAuthenticated($player)) {
            // ログイン認証済みメッセージ表示
            $player->sendMessage(TextFormat::GREEN . $this->main->getMessage("loginAlready"));
        } else {
            // ログインまたはアカウント登録してくれメッセージを表示
            $this->needAuthMessage($player, true);
        }
    }

    /*
     * プレイヤーがログアウトしたときのイベント
     */
    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerQuit: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // コマンドフックをクリア
        $this->invoker->getHookQueue()->clear($player);

        unset($this->lastSendMessageTime[$player->getRawUniqueId()]);
    }

    /*
     * プレイヤーが移動したときのイベント
     */
    public function onPlayerMove(PlayerMoveEvent $event)
    {
        // $this->main->getLogger()->debug("onPlayerMove: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        if ($this->cancelEventIfNotAuth($event, $player)) {
            $event->getPlayer()->onGround = true;
        }
    }

    /*
     * プレイヤーがインタラクションしたときのイベント
     */
    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerInteract: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * プレイヤーがアイテムを置いたときのイベント
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerPreLogin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * プレイヤーがアイテムを装備したときのイベント
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerItemConsume: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * ダメージを受けたときのイベント
     */
    public function onEntityDamage(EntityDamageEvent $event)
    {
        $this->main->getLogger()->debug("onEntityDamage: ");

        $entity = $event->getEntity();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $entity);
    }

    /*
     * ブロックを破壊したときのイベント
     */
    public function onBlockBreak(BlockBreakEvent $event)
    {
        $this->main->getLogger()->debug("onBlockBreak: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * ブロックを設置したときのイベント
     */
    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $this->main->getLogger()->debug("onBlockPlace: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * インベントリを開くときのイベント
     */
    public function onInventoryOpen(InventoryOpenEvent $event)
    {
        $this->main->getLogger()->debug("onInventoryOpen: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * アイテムを拾ったときのイベント
     */
    public function onPickupItem(InventoryPickupItemEvent $event)
    {
        $this->main->getLogger()->debug("onPickupItem: ");

        $holder = $event->getInventory()->getHolder();

        if ($holder instanceof Player) {
            // 未認証ならイベントをキャンセル
            $player = $this->castInventoryHolderToPlayer($holder);
            $this->cancelEventIfNotAuth($event, $player);

        }
    }

    private function castInventoryHolderToPlayer(InventoryHolder $holder) : Player
    {
        return $holder;
    }

    /*
     * 認証していいない場合にイベントをキャンセル
     */
    private function cancelEventIfNotAuth(Cancellable $event, CommandSender $sender) : bool
    {
        // Player ではない場合
        if (Main::isNotPlayer($sender)) {
            return false;
        }

        $player = Main::castCommandSenderToPlayer($sender);

        // 認証済みの場合
        if (Main::getInstance()->isAuthenticated($player)) {
            return false;
        }

        // イベントをキャンセル
        $event->setCancelled(true);

        // ログインまたはアカウント登録を催促
        $this->needAuthMessage($player);

        return true;
    }

    /*
     *　ログインまたはアカウント登録を催促するメッセージを表示
     */
    private function needAuthMessage(Player $player, bool $immediate = false)
    {
        $key = $player->getRawUniqueId();
        $now = new \DateTime();

        if (array_key_exists($key, $this->lastSendMessageTime)) {
            $lastTime = $this->lastSendMessageTime[$key];
            $interval = $now->diff($lastTime, true);

            if ($interval->s >= self::INTERVAL_SECONDS) {
                return;
            }
        }

        $this->lastSendMessageTime[$key] = $now;

        // アカウント登録状態に応じて表示するメッセージを切り替える
        if ($this->main->isRegistered($player)) {
            // ログインしてもらうメッセージ
            $player->sendMessage(TextFormat::YELLOW . $this->main->getMessage("login"));
            $player->sendMessage(TextFormat::YELLOW . $this->main->getMessage("loginUsage"));
        } else {
            // 未登録ならアカウント登録してもらうメッセージ
            $player->sendMessage(TextFormat::YELLOW . $this->main->getMessage("register"));
            $player->sendMessage(TextFormat::YELLOW . $this->main->getMessage("registerUsage"));
        }
    }
}