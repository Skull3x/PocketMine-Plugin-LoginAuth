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

class EventListener implements Listener
{
    private $main;

    /**
     * コンストラクタ
     * @param Main $main
     */
    public function __construct(Main $main)
    {
        $this->main = $main;
    }

    /**
     * プレイヤーがログインするときのイベント発生順序
     * onLogin
     * onPlayerPreLogin
     * onPlayerRespawn
     * onJoin
     * onPlayerJoin
     * @param PlayerPreLoginEvent $event
     */

    function onLogin(PlayerPreLoginEvent $event)
    {
        $this->main->getLogger()->debug("onLogin: ");
    }

    public function onPlayerPreLogin(PlayerPreLoginEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerPreLogin: ");
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
    public function onJoin(PlayerJoinEvent $event)
    {
        $this->main->getLogger()->debug("onJoin: ");

        $player = $event->getPlayer();
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerJoin: ");

        $player = $event->getPlayer();

        $this->main->isAuthenticated($player);
    }

    /**
     * プレイヤーがログアウトしたときのイベント
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerQuit: ");

        $player = $event->getPlayer();

        $this->main->removeCache($player);
    }


    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerCommand: ");

        // イベントからプレイヤーを取得
        $player = $event->getPlayer();

        // プレイヤーが入力したメッセージを取得
        $message = $event->getMessage();

        // 先頭が /（スラッシュ）でなければ
        if (strpos($message, "/") !== 0) {
            return;
        }

        // メッセージから先頭スラッシュを除去して、空白文字で分割
        $args = explode(" ", substr($message, 1));
        $command = strtolower(array_shift($args) ?? "");

        switch ($command) {
            case "register":
                $password = array_shift($args) ?? "";
                $this->main->register($player, $password);
                $event->setCancelled(true);
                break;

            case "login":
                $password = array_shift($args) ?? "";
                $this->main->login($player, $password);
                $event->setCancelled(true);
                break;

            case "auth":
                $this->dispatchSubCommand($player, $args);
                $event->setCancelled(true);
                break;
        }
    }

    private function dispatchSubCommand(Player $player, array $args)
    {
        $subCommand = strtolower(array_shift($args) ?? "");

        switch ($subCommand) {
            case "unregister":
                $password = array_shift($args) ?? "";
                $this->main->unregister($player, $password);
                break;

            case "forget":
                $newPassword = array_shift($args) ?? "";
                $this->main->forget($player, $newPassword);
                break;

            case "password":
                $newPassword = array_shift($args) ?? "";
                $this->main->changePassword($player, $newPassword);
                break;

            default:
                $this->main->sendHelp($player);
        }
    }

    /**
     * プレイヤーが移動したときのイベント
     * @param PlayerMoveEvent $event
     */
    public function onPlayerMove(PlayerMoveEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerMove: ");

        $player = $event->getPlayer();
    }

    /**
     * プレイヤーがインタラクションしたときのイベント
     * @param PlayerInteractEvent $event
     */
    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerInteract: ");

        $player = $event->getPlayer();
    }

    /**
     * プレイヤーがアイテムを置いたときのイベント
     * @param PlayerDropItemEvent $event
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerPreLogin: ");

        $player = $event->getPlayer();
    }

    /**
     * プレイヤーがアイテムを装備したときのイベント
     * @param PlayerItemConsumeEvent $event
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event)
    {
        $this->main->getLogger()->debug("onPlayerItemConsume: ");

        $player = $event->getPlayer();
    }


    /**
     * エンティティのダメージを受けたときのイベント
     * @param EntityDamageEvent $event
     */
    public function onEntityDamage(EntityDamageEvent $event)
    {
        $this->main->getLogger()->debug("onEntityDamage: ");
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBlockBreak(BlockBreakEvent $event)
    {
        $this->main->getLogger()->debug("onBlockBreak: ");

        $player = $event->getPlayer();
    }

    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $this->main->getLogger()->debug("onBlockPlace: ");
    }

    public function onInventoryOpen(InventoryOpenEvent $event)
    {
        $this->main->getLogger()->debug("onInventoryOpen: ");
    }

    public function onPickupItem(InventoryPickupItemEvent $event)
    {
        $this->main->getLogger()->debug("onPickupItem: ");

        $player = $event->getInventory()->getHolder();
    }
}
