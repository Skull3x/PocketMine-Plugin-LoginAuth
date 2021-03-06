<?php

namespace Jhelom\LoginAuth;


use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;

/*
 * イベントリスナー
 */

class EventListener implements Listener
{
    // 「ログインして」や「登録して」メッセージの表示間隔（秒単位）
    const INTERVAL_SECONDS = 10;

    // メッセージ表示間隔用に最終表示時間の履歴を保持するリスト
    private $lastSendMessageTime = [];

    // プレイヤーがジョインしたときの座標
    private $playerJoinPosition = [];

    /*
     * プレイヤーがログインするときのイベント
     */
    public function onLogin(PlayerPreLoginEvent $event)
    {
        $player = $event->getPlayer();
        $name = strtolower($player->getName());

        // 重複ログインを禁止するために、既に別端末からログインしていたら拒否する

        // ログイン中の全プレイヤーの一覧を取得
        $onlinePlayerList = Main::getInstance()->getServer()->getOnlinePlayers();

        // ログイン中の全プレイヤーをループ
        foreach ($onlinePlayerList as $onlinePlayer) {
            // 自分自身なら処理をスキップ
            if ($onlinePlayer->getRawUniqueId() === $player->getRawUniqueId()) {
                continue;
            }

            // ログイン中のプレイヤーの名前を小文字に変換
            $onlinePlayerName = strtolower($onlinePlayer->getName());

            // 名前が同じで
            if ($onlinePlayerName === $name) {
                // ログイン認証済みなら
                if (Main::getInstance()->isAuthenticated($onlinePlayer)) {
                    // イベントをキャンセル
                    $event->setCancelled(true);

                    // 拒否する
                    $player->close("", Main::getInstance()->getMessage("loginMultiDeviceError"));
                    return;
                }
            }
        }
    }

    /*
     * プレイヤーがゲームに参加するときのイベント
     */
    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();

        // 認証済みなら
        if (Main::getInstance()->isAuthenticated($player)) {
            // ログイン認証済みメッセージ表示
            Main::getInstance()->sendMessageResource($player, "loginAlready");
        } else {
            // 座標を記録
            $this->saveJoinPosition($player);

            // ログインまたはアカウント登録してくれメッセージを表示
            $this->needAuthMessage($player, true);
        }
    }

    /*
     * プレイヤーがログアウトしたときのイベント
     */

    private function saveJoinPosition(Player $player)
    {
        $key = $player->getRawUniqueId();
        $value = $player->getFloorX() . "/" . $player->getFloorZ();
        $this->playerJoinPosition[$key] = $value;
    }

    private function needAuthMessage(Player $player, bool $immediate = false)
    {
        $key = $player->getRawUniqueId();

        // 現在日時を取得
        $now = new \DateTime();

        // 強制フラグが立っていた場合
        if ($immediate) {
            // 最終表示時刻の履歴を削除
            unset($this->lastSendMessageTime[$key]);
        }

        // キーが存在する場合
        if (array_key_exists($key, $this->lastSendMessageTime)) {
            // 最終表示時刻を履歴から主tく
            $lastTime = $this->lastSendMessageTime[$key];

            // 時差を取得
            $interval = $now->diff($lastTime, true);

            // 時差が一定時間経過後の場合
            if ($interval->s <= self::INTERVAL_SECONDS) {
                // 何もしないでリターン
                return;
            }
        }

        // 最終表示時刻の履歴を更新
        $this->lastSendMessageTime[$key] = $now;

        // アカウント登録状態に応じて表示するメッセージを切り替える
        if (Main::getInstance()->isRegistered($player)) {
            // ログインしてもらうメッセージ
            $player->sendMessage(Main::getInstance()->getMessage("login"));
            $player->sendMessage(Main::getInstance()->getMessage("loginUsage"));
        } else {
            // 未登録ならアカウント登録してもらうメッセージ
            $player->sendMessage(Main::getInstance()->getMessage("register"));
            $player->sendMessage(Main::getInstance()->getMessage("registerUsage"));
        }
    }

    /*
     * チャットイベント
     */

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();

        Main::getInstance()->getLoginCache()->remove($player);
        $key = $player->getRawUniqueId();
        unset($this->lastSendMessageTime[$key]);
        $this->removeJoinPosition($player);
    }

    /*
     * プレイヤーが移動したときのイベント
     */

    private function removeJoinPosition(Player $player)
    {
        $key = $player->getRawUniqueId();
        unset($this->playerJoinPosition[$key]);
    }

    /*
     * プレイヤーがインタラクションしたときのイベント
     */

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $player = $event->getPlayer();

        if (Main::getInstance()->isAuthenticated($player)) {
            return;
        }

        $msg = $event->getMessage();

        foreach (Main::getInstance()->getInvoker()->getNames() as $name) {
            if (strpos($msg, "/" . $name) === 0) {
                return;
            }
        }

        $event->setCancelled();

        Main::getInstance()->sendMessageResource($player, "commandNeedAuth");
    }

    /*
     * プレイヤーがアイテムを置いたときのイベント
     */

    public function onChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        if ($this->cancelEventIfNotAuth($event, $player)) {
            $recipients = $event->getRecipients();

            // チャットの受信者をクリア
            foreach ($recipients as $key => $recipient) {
                if ($recipient instanceof Player) {
                    unset($recipients[$key]);
                }
            }
        }
    }

    /*
     * プレイヤーがアイテムを装備したときのイベント
     */

    private function cancelEventIfNotAuth(Cancellable $event, CommandSender $sender) : bool
    {
        // Player ではない場合
        if (!($sender instanceof Player)) {
            return false;
        }

        $player = Main::castToPlayer($sender);

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
     * ダメージを受けたときのイベント
     */

    public function onPlayerMove(PlayerMoveEvent $event)
    {
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        if ($this->cancelEventIfNotAuth($event, $player)) {
            // 落下しようとしている(Yの移動）場合、画面がカクカクするので
            // ゲーム参加時のX,Zと同じならイベントのキャンセルを取消（つまりイベントをキャンセルしない）
            if ($this->compareJoinPosition($player)) {
                $event->setCancelled(false);
            } else {
                $event->getPlayer()->onGround = true;
            }
        }
    }

    /*
     * Entity を Player にタイプヒンティングを利用して疑似的にキャスト
     * というか、そもそもクラス間に継承関係はないようだが…
     */

    private function compareJoinPosition(Player $player) : bool
    {
        $key = $player->getRawUniqueId();
        $value = $player->getFloorX() . "/" . $player->getFloorZ();
        return $value === $this->playerJoinPosition[$key];
    }

    /*
     * ブロックを破壊したときのイベント
     */

    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * ブロックを設置したときのイベント
     */

    public function onPlayerDropItem(PlayerDropItemEvent $event)
    {
        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * インベントリを開くときのイベント
     */

    public function onPlayerItemConsume(PlayerItemConsumeEvent $event)
    {
        // プレイヤーを取得
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * アイテムを拾ったときのイベント
     */

    public function onEntityDamage(EntityDamageEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            // 未認証ならイベントをキャンセル
            $this->cancelEventIfNotAuth($event, $this->castEntityToPlayer($entity));
        }
    }

    /*
     * 認証していいない場合にイベントをキャンセル
     */

    private function castEntityToPlayer($entity) : Player
    {
        return $entity;
    }

    /*
     *　ログインまたはアカウント登録を催促するメッセージを表示
     * 短時間に連続表示のうっとうしさ抑制するために一定時間経過後の表示するようにする
     */

    public function onBlockBreak(BlockBreakEvent $event)
    {
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * 座標を保存
     */

    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * 座標を比較
     */

    public function onInventoryOpen(InventoryOpenEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onInventoryOpen: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * 座標を削除
     */

    public function onPickupItem(InventoryPickupItemEvent $event)
    {
        $holder = $event->getInventory()->getHolder();

        if ($holder instanceof Player) {
            // 未認証ならイベントをキャンセル
            $player = Main::castToPlayer($holder);
            $this->cancelEventIfNotAuth($event, $player);
        }
    }
}
