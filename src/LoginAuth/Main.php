<?php

namespace LoginAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

require_once("Account.php");

class Main extends PluginBase
{
    // データベース
    private $pdo;

    // リスナー
    private $listener;

    // タスク
    private $task;

    // セキュリティスタンプのキャッシ
    private $securityStampCacheList = [];

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

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     */
    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        $this->getLogger()->debug("onCommand: ");
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
        $sql = "SELECT * FROM account WHERE name = :name";
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
            $player->sendMessage(TextFormat::GREEN . "既にログイン認証済みです");
            // リターン
            return false;
        }

        // パスワード検証
        if (!$this->validatePassword($player, $password, "パスワードを入力してください。/register <password>")) {
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

            $player->sendMessage(TextFormat::RED . "１つの端末で登録可能なアカウント数の上限は" . $accountSlot . "です。この端末では登録上限に達しているため、もうこれ以上アカウントを登録することはできません。");
            $player->sendMessage(TextFormat::RED . "この端末で登録されていアカウントの一覧は次の通りです。名前を変更してログインしなおしてください。" . $nameListStr);

            // リターン
            return false;
        }

        // 名前を小文字に変換
        $name = strtolower($player->getName());

        // 名前でデータベースからアカウントを検索
        $account = $this->findAccountByName($name);

        // データベースに同じ名前のアカウントが既に存在する場合
        if (!$account->isNull) {
            $player->sendMessage(TextFormat::RED . "名前 " . $player->getName() . "は既に登録されています。別の名前に変更してください");
            // リターン
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

        // キャッシュに登録
        $this->addCache($player);

        // メッセージ表示タスクからプレイヤーを削除
        $this->getTask()->removePlayer($player);

        $player->sendMessage(TextFormat::GREEN . "アカウント登録しました");

        return true;
    }

    /**
     * 認証済みなら true を返す
     * @param Player $player
     * @return bool
     */
    public function isAuthenticated(Player $player) :bool
    {
        // セキュリティスタンプを生成
        $securityStamp = $this->makeSecurityStamp($player);

        // キーを生成
        $key = $this->makeCacheKey($player);

        // キャッシュにキーが存在するなら
        if (array_key_exists($key, $this->securityStampCacheList)) {
            // キャッシュのセキュリティスタンプと比較して同じなら
            if ($this->securityStampCacheList[$key] === $securityStamp) {
                // 認証済みを示す true を返す
                return true;
            }
        }

        // 名前をもとにアカウントをデータベースから検索
        $account = $this->findAccountByName(strtolower($player->getName()));

        // アカウントがアカウントが存在する
        if (!$account->isNull) {
            // セキュリティスタンプを比較して同じなら
            if ($account->securityStamp === $securityStamp) {
                // キャッシュに登録して
                $this->addCache($player);

                // 認証済みを示す true を返す
                return true;
            }
        }

        // 未認証を示す false を返す
        return false;
    }

    /**
     * セキュリティスタンプを作成
     * @param Player $player
     * @return string
     */
    private function makeSecurityStamp(Player $player) : string
    {
        // 名前
        $name = strtolower($player->getName());

        // 端末ID
        $clientId = $player->getClientId();

        // IPアドレス
        $ip = $player->getAddress();

        // 連結
        $seed = $name . $clientId . $ip;

        // ハッシュ
        return hash("sha256", $seed);
    }

    /**
     * キャッシュのキーを生成
     *
     * @param Player $player
     * @return string
     */
    private function makeCacheKey(Player $player)
    {
        return $player->getRawUniqueId();
    }

    /**
     * キャッシュに登録
     *
     * @param Player $player
     */
    public function addCache(Player $player)
    {
        $key = $this->makeCacheKey($player);
        $securityStamp = $this->makeSecurityStamp($player);
        $this->securityStampCacheList[$key] = $securityStamp;
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
            $player->sendMessage(TextFormat::RED . "パスワードは" . $passwordLengthMin . "文字以上にしてください");
            return false;
        }

        // パスワードが長い場合
        if ($passwordLength > $passwordLengthMax) {
            $player->sendMessage(TextFormat::RED . "パスワードは" . $passwordLengthMax . "文字以下にしてください");
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

        // データベースからクラスとして取得
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
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function unregister(Player $player, string $password) :bool
    {
        $account = $this->findAccountByName($player);

        if ($account->isNull) {
            $player->sendMessage("アカウントが見つかりません");
            return false;
        }

        $passwordHash = $this->makePasswordHash($password);

        if ($account->passwordHash !== $passwordHash) {
            $player->sendMessage("パスワードが違います。アカウントを削除できませんでした。");

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
     * キャッシュを削除
     *
     * @param Player $player
     */
    public function removeCache(Player $player)
    {
        $key = $this->makeCacheKey($player);
        unset($this->securityStampCacheList[$key]);
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

        if (!$this->validatePassword($player, $password, "パスワードを入力してください。/login <password>")) {
            return false;
        }

        // 名前をもとにデータベースからアカウントを検索する
        $account = $this->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            $player->sendMessage(TextFormat::RED . "はじめにアカウント登録してください。/register <password>");
            return false;
        }

        $passwordHash = $this->makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            $player->sendMessage(TextFormat::RED . "パスワードが違います。");
            return false;
        }

        // セキュリティスタンプを更新
        $securityStamp = $this->makeSecurityStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // キャッシュに登録
        $this->addCache($player);

        // メッセージ表示タスクからプレイヤーを削除
        $this->getTask()->removePlayer($player);

        $player->sendMessage(TextFormat::GREEN . "ログイン認証しました");

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

        if (!$this->validatePassword($player, $newPassword, "新しいパスワードを入力してください。/auth password <newPassword>")) {
            return false;
        }

        $name = strtolower($player->getName());

        $sql = "UPDATE account SET passwordHash = :passwordHash WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($newPassword));
        $stmt->execute();

        $player->sendMessage(TextFormat::GREEN . "パスワードを設定しました。");

        return true;
    }
}

?>