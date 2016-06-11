<?php

namespace Jhelom\LoginAuth;

use Jhelom\LoginAuth\CommandReceivers\AuthCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\LoginCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\PasswordChangeCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\RegisterCommandReceiver;
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
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\inventory\InventoryHolder;
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

    // インボーカー
    private $invoker;

    // プレイヤーがジョインしたときの座標
    private $playerJoinPosition = [];

    /*
     * コンストラクタ
     */
    public function __construct()
    {
        // インボーカーを初期化
        $this->invoker = new CommandInvoker();
        $this->invoker->add(new RegisterCommandReceiver());
        $this->invoker->add(new LoginCommandReceiver());
        $this->invoker->add(new AuthCommandReceiver());
        $this->invoker->add(new PasswordChangeCommandReceiver());
    }

    /*
     * プレイヤーがコマンドを実行するときのイベント
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onPlayerCommand: " . $event->getPlayer()->getName());

        // インボーカーでコマンドを処理
        $this->invoker->invokePlayerCommand($event);

        // コマンドプレフィックスが付いていない場合
        if (strpos($event->getMessage(), "/") !== 0) {
            return;
        }

        // イベントがキャンセルされている場合
        if ($event->isCancelled()) {
            return;
        }

        // プレイヤーを取得
        $player = $event->getPlayer();

        // ログイン認証されている場合
        if (Main::getInstance()->isAuthenticated($player)) {
            return;
        }

        // 「ログイン認証しないとコマンドは実行できません」メッセージを表示
        $player->sendMessage(Main::getInstance()->getMessage("commandNeedAuth"));
        $this->needAuthMessage($player);

        // イベントをキャンセル
        $event->setCancelled(true);
    }

    /*
     * コンソールからコマンドを実行するときのイベント
     */
    public function onServerCommand(ServerCommandEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onServerCommand: " . $event->getSender()->getName());

        $this->invoker->invokeServerCommand($event);
    }

    /*
     * プレイヤーがログインするときのイベント
     */
    public function onPlayerPreLogin(PlayerPreLoginEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onPlayerPreLogin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 名前を小文字に変換
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
    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onPlayerJoin: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // 認証済みなら
        if (Main::getInstance()->isAuthenticated($player)) {
            // ログイン認証済みメッセージ表示
            $player->sendMessage(Main::getInstance()->getMessage("loginAlready"));
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
    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onPlayerQuit: ");

        // プレイヤーを取得
        $player = $event->getPlayer();

        // コマンドフックをクリア
        CommandHookManager::getInstance()->clear($player);

        $key = $player->getRawUniqueId();
        unset($this->lastSendMessageTime[$key]);

        // 座標を削除
        $this->removeJoinPosition($player);
    }

    /*
     * チャットイベント
     */
    public function onChat(PlayerChatEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onChat: " . $event->getPlayer() . ": " . $event->getMessage());

        // プレイヤーを取得
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
     * プレイヤーが移動したときのイベント
     */
    public function onPlayerMove(PlayerMoveEvent $event)
    {
        // $this->main->getLogger()->debug("onPlayerMove: ");

        // プレイヤーを取得
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
     * プレイヤーがインタラクションしたときのイベント
     */
    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onPlayerInteract: ");

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
        Main::getInstance()->getLogger()->debug("onPlayerPreLogin: ");

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
        Main::getInstance()->getLogger()->debug("onPlayerItemConsume: ");

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
        // $this->main->getLogger()->debug("onEntityDamage: ");

        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            // 未認証ならイベントをキャンセル
            $this->cancelEventIfNotAuth($event, $this->castEntityToPlayer($entity));
        }
    }

    /*
     * Entity を Player にタイプヒンティングを利用して疑似的にキャスト
     * というか、そもそもクラス間に継承関係はないようだが…
     */
    private function castEntityToPlayer($entity) : Player
    {
        return $entity;
    }

    /*
     * ブロックを破壊したときのイベント
     */
    public function onBlockBreak(BlockBreakEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onBlockBreak: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * ブロックを設置したときのイベント
     */
    public function onBlockPlace(BlockPlaceEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onBlockPlace: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * インベントリを開くときのイベント
     */
    public function onInventoryOpen(InventoryOpenEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onInventoryOpen: ");

        $player = $event->getPlayer();

        // 未認証ならイベントをキャンセル
        $this->cancelEventIfNotAuth($event, $player);
    }

    /*
     * アイテムを拾ったときのイベント
     */
    public function onPickupItem(InventoryPickupItemEvent $event)
    {
        Main::getInstance()->getLogger()->debug("onPickupItem: ");

        $holder = $event->getInventory()->getHolder();

        if ($holder instanceof Player) {
            // 未認証ならイベントをキャンセル
            $player = $this->castInventoryHolderToPlayer($holder);
            $this->cancelEventIfNotAuth($event, $player);

        }
    }

    /*
     * InventoryHolder を Player にタイプヒンティングで疑似的にキャスト
     */
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
        if (!($sender instanceof Player)) {
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
     * 短時間に連続表示のうっとうしさ抑制するために一定時間経過後の表示するようにする
     */
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
     * 座標を保存
     */
    private function saveJoinPosition(Player $player)
    {
        $key = $player->getRawUniqueId();
        $value = $player->getFloorX() . "/" . $player->getFloorZ();
        $this->playerJoinPosition[$key] = $value;
    }

    /*
     * 座標を比較
     */
    private function compareJoinPosition(Player $player) : bool
    {
        $key = $player->getRawUniqueId();
        $value = $player->getFloorX() . "/" . $player->getFloorZ();
        return $value === $this->playerJoinPosition[$key];
    }

    /*
     * 座標を削除
     */
    private function removeJoinPosition(Player $player)
    {
        $key = $player->getRawUniqueId();
        unset($this->playerJoinPosition[$key]);
    }
}
