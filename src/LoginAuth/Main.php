<?php

namespace LoginAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

require_once("Account.php");
require_once("SecurityStampManager.php");

class Main extends PluginBase
{
    // データベース
    private $pdo;

    // リスナー
    private $listener;

    // タスク
    private $task;

    // メッセージリソース
    private $messageResource;

    // セキュリティスタンプ
    private $securityStampManager;

    // データベース初期化SQL
    private $ddl = <<<_SQL_
CREATE TABLE [account] (
[name] TEXT NOT NULL UNIQUE,
[clientId] TEXT NOT NULL,
[ip] TEXT NOT NULL,
[passwordHash] TEXT NOT NULL,
[securityStamp] TEXT NOT NULL,
PRIMARY KEY(name)
);                
_SQL_;

    /**
     * プラグインが有効化されたときのイベント
     */
    public function onEnable()
    {
        $this->getLogger()->info("§a Designed by jhelom & dragon7");

        // デフォルト設定をセーブ
        $this->saveDefaultConfig();

        // 設定をロード
        $this->reloadConfig();

        // メッセージリソースを初期化
        $this->messageResource = new MessageResource($this);

        // セキュリティスタンプマネージャーを初期化
        $this->securityStampManager = new SecurityStampManager();

        // データベースに接続
        $this->openDatabase();

        // プラグインマネージャーに登録してイベントを受信
        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);

        // タスクをスケジューラーに登録
        $this->task = new ShowMessageTask($this);
        $ticks = 20 * 60; // 1分
        $this->getServer()->getScheduler()->scheduleRepeatingTask($this->task, $ticks);
    }

    /**
     * データベースに接続
     */
    private function openDatabase()
    {
        // データベースファイルのパスを組み立て
        $path = rtrim($this->getDataFolder(), "/") . DIRECTORY_SEPARATOR . "account.db";

        // データベースファイルが不在なら、初期化フラグを立てる
        $isInitializing = !file_exists($path);

        // 接続文字列を組み立て
        $connectionString = "sqlite:" . $path;

        // データベース接続
        $this->pdo = new \PDO($connectionString);

        // SQLエラーで例外をスローするように設定
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 初期化フラグが立っていたら
        if ($isInitializing) {
            // テーブルを作成
            $this->pdo->exec($this->ddl);
        }
    }

    /**
     * プラグインが無効化されたときのイベント
     */
    public function onDisable()
    {
        $this->task = null;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        $this->getLogger()->debug("onCommand: ");
    }

    public function getMessage() : MessageResource
    {
        return $this->messageResource;
    }

    /**
     * アカウント登録済みなら true を返す
     * @param Player $player
     * @return bool
     */
    function isRegistered(Player $player) : bool
    {
        $account = $this->findAccountByName($player->getName());

        return $account->isNull === false;
    }

    /**
     * 名前をもとにデータベースからアカウントを検索する
     * 不在の場合は isNullフィールドが true のアカウントを返す
     *
     * @param string $name
     * @return account
     */
    private function findAccountByName(string $name) : Account
    {
        $sql = "SELECT * FROM account WHERE name = :name ORDER BY name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($name), \PDO::PARAM_STR);
        $stmt->execute();

        // データベースからクラスとして取得
        $account = $stmt->fetchObject("LoginAuth\\Account");

        // 検索結果が０件の場合は false なので
        if ($account === false) {
            // isNull が true の Account を返す
            return new Account(true);
        }

        // データベースから取得したクラスを返す
        return $account;
    }

    /**
     * SQLプリペアドステートメント
     * @param string $sql
     * @return \PDOStatement
     */
    private function preparedStatement(string $sql) : \PDOStatement
    {
        return $this->getDatabase()->prepare($sql);
    }

    /**
     * データベースを取得
     * @return \PDO
     */
    private function getDatabase() : \PDO
    {
        return $this->pdo;
    }

    /**
     * アカウントを登録する
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function register(Player $player, string $password) :bool
    {
        // 認証済みなら
        if ($this->isAuthenticated($player)) {
            $player->sendMessage(TextFormat::GREEN . $this->getMessage()->alreadyLogin());
            return false;
        }

        // パスワード検証
        if (!$this->validatePassword($player, $password, $this->getMessage()->getPasswordRequired())) {
            // 失敗ならリターン
            return false;
        }

        // 端末IDをもとにデータベースからアカウント一覧を取得
        $accountList = $this->findAccountsByClientId($player->getClientId());

        // アカウント一覧の数
        $accountListCount = count($accountList);

        // 端末毎のアカウント上限数を取得（最低１以上で補正）
        $accountSlot = min(1, $this->getConfig()->get("accountSlot"));

        // アカウント上限数を超過している場合
        if ($accountSlot < $accountListCount) {
            // 名前一覧を組み立てるための配列
            $nameList = [];

            // アカウント一覧をループ
            foreach ($accountList as $account) {
                // 名前一覧に追加
                array_push($nameList, $account->name);
            }

            // 名前一覧をカンマで連結
            $nameListStr = $name = implode(",", $nameList);

            $player->sendMessage(TextFormat::RED . $this->getMessage()->accountSlotOver1($accountSlot));
            $player->sendMessage(TextFormat::RED . $this->getMessage()->accountSlotOlver2());
            $player->sendMessage(TextFormat::RED . $nameListStr);

            return false;
        }

        // 名前を小文字に変換
        $name = strtolower($player->getName());

        // 名前でデータベースからアカウントを検索
        $account = $this->findAccountByName($name);

        // データベースに同じ名前のアカウントが既に存在する場合
        if (!$account->isNull) {
            $player->sendMessage(TextFormat::RED . $this->getMessage()->alreadyExistsName($player->getName()));
            return false;
        }

        //　データベースに登録
        $sql = "INSERT INTO account (name, clientId, ip, passwordHash, securityStamp) VALUES (:name, :clientId, :ip, :passwordHash, :securityStamp)";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $this->makeSecurityStamp($player), \PDO::PARAM_STR);
        $stmt->execute();

        $this->securityStampManager->add($player);

        // メッセージ表示タスクからプレイヤーを削除
        $this->getTask()->removePlayer($player);

        $player->sendMessage(TextFormat::GREEN . $this->getMessage()->registerSuccessful());

        return true;
    }

    /**
     * 認証済みなら true を返す
     * @param Player $player
     * @return bool
     */
    public function isAuthenticated(Player $player) :bool
    {
        if ($this->securityStampManager->validate($player)) {
            // 認証済みを示す true を返す
            return true;
        }

        // 名前をもとにアカウントをデータベースから検索
        $account = $this->findAccountByName(strtolower($player->getName()));

        // アカウントがアカウントが存在する
        if (!$account->isNull) {
            // セキュリティスタンプを比較して同じなら
            if ($account->securityStamp === $this->securityStampManager->makeStamp($player)) {
                $this->securityStampManager->add($player);

                // 認証済みを示す true を返す
                return true;
            }
        }

        // 未認証を示す false を返す
        return false;
    }

    /**
     * パスワードを検証、成功なら true、失敗なら false を返す
     *
     * @param Player $player
     * @param string $password
     * @param string $emptyErrorMessage
     * @return bool
     */
    public function validatePassword(Player $player, string $password, string $emptyErrorMessage = "パスワードを入力してください") : bool
    {
        if ($password === "") {
            $player->sendMessage(TextFormat::RED . $emptyErrorMessage);
            return false;
        }

        // 設定ファイルからパスワードの文字数の下限を取得
        $passwordLengthMin = $this->getConfig()->get("passwordLengthMin");

        // 設定ファイルからパスワードの文字数の上限を取得
        $passwordLengthMax = $this->getConfig()->get("passwordLengthMax");

        // パスワードの文字数を取得
        $passwordLength = strlen($password);

        // パスワードが短い場合
        if ($passwordLength < $passwordLengthMin) {
            $player->sendMessage(TextFormat::RED . $this->getMessage()->passwordLengthMin($passwordLengthMin));
            return false;
        }

        // パスワードが長い場合
        if ($passwordLength > $passwordLengthMax) {
            $player->sendMessage(TextFormat::RED . $this->getMessage()->passwordLengthMax($passwordLengthMax));
            return false;
        }

        return true;
    }

    /**
     * 端末IDをもとにデータベースからアカウントを検索して、Accountクラスの配列を返す
     * 不在の場合は、空の配列を返す
     *
     * @param string $clientId
     * @return array
     */
    private function findAccountsByClientId(string $clientId) : array
    {
        $sql = "SELECT * FROM account WHERE clientId = :clientId ORDER BY name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":clientId", $clientId, \PDO::PARAM_STR);
        $stmt->execute();

        // データベースからクラスの配列として取得
        $results = $stmt->fetchAll(\PDO::FETCH_CLASS, "LoginAuth\\Account");

        return $results;
    }

    /**
     * パスワードハッシュを生成する
     * @param string $password
     * @return string
     */
    private function makePasswordHash(string $password) : string
    {
        return hash("sha256", $password);
    }

    /**
     * @return ShowMessageTask
     */
    public function getTask() : ShowMessageTask
    {
        return $this->task;
    }

    /**
     * アカウントを削除する
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function unregister(Player $player, string $password) :bool
    {
        $account = $this->findAccountByName($player);

        if ($account->isNull) {
            $player->sendMessage(TextFormat::RED . $this->getMessage()->unregisterNotFound());
            return false;
        }

        $passwordHash = $this->makePasswordHash($password);

        if ($account->passwordHash !== $passwordHash) {
            $player->sendMessage(TextFormat::RED . $this->getMessage()->unregisterPasswordError());

            // 異常終了を示す false を返す
            return false;
        }

        $sql = "DELETE account WHERE name = :name AND passwordHash = :passwordHash";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->execute();

        // セッション削除
        $this->removeCache($player);

        // プレイヤーを強制ログアウト
        $player->close("アカウントを削除しました。");

        return true;
    }

    /**
     * ログイン
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function login(Player $player, string $password):bool
    {
        // 空白文字を除去
        $password = trim($password);

        // パスワードを検証
        if (!$this->validatePassword($player, $password, $this->getMessage()->passwordRequired())) {
            // 検証失敗ならリターン
            return false;
        }

        // 名前をもとにデータベースからアカウントを検索する
        $account = $this->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            // メッセージを表示してリターン
            $player->sendMessage(TextFormat::RED . $this->getMessage()->registerFirst());
            return false;
        }

        // パスワードハッシュを生成
        $passwordHash = $this->makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            // パスワード不一致メッセージを表示してリターン
            $player->sendMessage(TextFormat::RED . $this->getMessage()->passwordError());
            return false;
        }

        // データベースのセキュリティスタンプを更新
        $securityStamp = $this->securityStampManager->makeStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        $this->securityStampManager->add($player);

        // メッセージ表示タスクからプレイヤーを削除
        $this->getTask()->removePlayer($player);

        // ログイン成功メッセージを表示
        $player->sendMessage(TextFormat::GREEN . $this->getMessage()->loginSuccessful());

        // 正常終了を示す true を返す
        return true;
    }

    /**
     * パスワードを変更
     *
     * @param Player $player
     * @param string $newPassword
     * @return bool
     */
    public function changePassword(Player $player, string $newPassword) : bool
    {
        $newPassword = trim($newPassword);

        if (!$this->validatePassword($player, $newPassword, $this->getMessage()->passwordChangeRequired())) {
            return false;
        }

        $name = strtolower($player->getName());

        $sql = "UPDATE account SET passwordHash = :passwordHash WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($newPassword));
        $stmt->execute();

        $player->sendMessage(TextFormat::GREEN . $this->getMessage()->passwordChangeSuccessful());

        return true;
    }
}

?>