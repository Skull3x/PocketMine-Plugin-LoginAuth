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

    // セキュリティスタンプをキャッシ
    private $cacheList = [];

    // データベース初期化SQL
    private $ddl = <<<_SQL_
CREATE TABLE [account] (
[name] TEXT NOT NULL UNIQUE,
[clientId] TEXT NOT NULL,
[ip] TEXT NOT NULL,
[passwordHash] TEXT NOT NULL,
[securityStamp] TEXT,
PRIMARY KEY(name)
);                
_SQL_;

    /**
     * プラグインが有効化された時のイベント
     */
    public function onEnable()
    {
        $this->getLogger()->info("§a Designed by Jhelom & Dragon7");

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
        $ticks = 20 * 30; // 1分
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

        $this->getLogger()->debug("ConnectionString = " . $connectionString);

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

    public function sendHelp(Player $player)
    {
        $player->sendMessage("パスワード忘れ /auth forget");
        $player->sendMessage("パスワード変更 /auth password <newPassword>");
        $player->sendMessage("アカウント削除 /auth unregister <password>");
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function verifyAuthenticated(Player $player) : bool
    {
        if ($this->isAuthenticated($player)) {
            return true;
        }

        if ($this->isRegistered($player)) {
            $player->sendMessage("登録してください");
        } else {
            $player->sendMessage("ログインしてください");
        }

        return false;
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
        if (array_key_exists($key, $this->cacheList)) {
            // キャッシュのセキュリティスタンプと比較して同じなら
            if ($this->cacheList[$key] === $securityStamp) {
                // 認証済みを示す true を返す
                return true;
            }
        }

        // 名前をもとにアカウントをデータベースから検索
        $account = $this->findAccountByName(strtolower($player->getName()));

        // アカウントがNULLではない（つまりアカウントが存在する）場合
        if ($account !== NULL) {
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
        $name = strtolower($player->getName());
        $clientId = $player->getClientId();
        $ip = $player->getAddress();

        return sha1($name . "@@" . $clientId . "@@" . $ip);
    }

    /**
     * キャッシュのキーを生成
     * @param Player $player
     * @return string
     */
    private function makeCacheKey(Player $player)
    {
        return $player->getRawUniqueId();
    }

    /**
     * 名前をもとにデータベースからアカウントを検索する、不在の場合は NULL を返す
     * @param string $name
     * @return account
     */
    private function findAccountByName(string $name) : Account
    {
        $sql = "SELECT * FROM account WHERE name = :name";
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt = $this->pdo->prepare($sql);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":name", strtolower($name), \PDO::PARAM_STR);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->execute();

        /** @noinspection PhpUndefinedMethodInspection */
        $account = $stmt->fetch(\PDO::FETCH_CLASS, "LoginAuth\\Account");

        // 検索結果が０件の場合は false なので
        if ($account === false) {
            // null をリターン
            return null;
        }

        return $account;
    }

    /**
     * キャッシュに登録
     * @param Player $player
     */
    public function addCache(Player $player)
    {
        $key = $this->makeCacheKey($player);
        $securityStamp = $this->makeSecurityStamp($player);
        $this->cacheList[$key] = $securityStamp;
    }

    /**
     * アカウント登録済みなら true を返す
     * @param Player $player
     * @return bool
     */
    function isRegistered(Player $player) : bool
    {
        $account = $this->findAccountByName($player->getName());

        return $account !== NULL;
    }

    /**
     * アカウントを登録する
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

        // パスワードが空の場合
        if ($password === "") {
            $player->sendMessage(TextFormat::GREEN . "アカウント登録は次のコマンドでpasswordの部分に自分で考えたパスワードを入力します");
            $player->sendMessage(TextFormat::GREEN . "/register <password>");
            // リターン
            return false;
        }

        // パスワードの文字数の下限を取得
        $passwordLengthMin = $this->getConfig()->get("passwordLengthMin");

        // パスワードが短い場合
        if (strlen($password) < $passwordLengthMin) {
            $player->sendMessage(TextFormat::RED . "パスワードは" . $passwordLengthMin . "文字以上にしてください");
            // リターン
            return false;
        }

        // 名前を小文字に変換
        $name = strtolower($player->getName());

        // 名前をもとにデータベースからアカウントを検索
        $account = $this->findAccountByName($name);

        // データベースに同じ名前のアカウントが既に存在する場合
        if ($account !== NULL) {
            $player->sendMessage(TextFormat::RED . "名前 " . $player->getName() . "は既に登録されています。別の名前に変更してください");
            // リターン
            return false;
        }

        // セキュリティスタンプ生成
        $securityStamp = $this->makeSecurityStamp($player);

        //　データベースに登録
        $sql = "INSERT INTO account (name, clientId, ip, passwordHash, securityStamp) VALUES (:name, :clientId, :ip, :passwordHash, :securityStamp)";
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt = $this->pdo->prepare($sql);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // キャッシュに登録
        $this->addCache($player);

        $player->sendMessage(TextFormat::AQUA . "アカウント登録しました。");

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
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt = $this->pdo->prepare($sql);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":clientId", $clientId, \PDO::PARAM_STR);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->execute();

        /** @noinspection PhpUndefinedMethodInspection */
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
        return sha1($password);
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

        if ($account === NULL) {
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
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->execute();

        // セッション削除
        $this->removeCache($player);

        // プレイヤーを強制ログアウト
        $player->kick("アカウントを削除しました。");

        return true;
    }

    /**
     * キャッシュを削除
     * @param Player $player
     */
    public function removeCache(Player $player)
    {
        $key = $this->makeCacheKey($player);
        unset($this->cacheList[$key]);
        $this->getTask()->addPlayer($player);
    }

    /**
     * @return ShowMessageTask
     */
    public function getTask() : ShowMessageTask
    {
        return $this->task;
    }

    /**
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function login(Player $player, string $password):bool
    {
        $account = $this->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account === NULL) {
            $player->sendMessage("アカウントを登録していません。次のコマンドを実行してアカウント登録をしてください。");
            $player->sendMessage("/register <password>");
            return false;
        }

        $passwordHash = $this->makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            $player->sendMessage("パスワードが違います。もしパスワードを忘れた場合は /forget で再設定できます（端末とIPが同じ場合のみ）");
            return false;
        }

        $securityStamp = $this->makeSecurityStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        $this->addCache($player);

        return true;
    }

    /**
     * パスワードを再設定
     * @param Player $player
     * @param string $newPassword
     * @return bool
     */
    public function forget(Player $player, string $newPassword) : bool
    {
        if ($this->isAuthenticated($player)) {
            $player->sendMessage("既にログイン認証済みです");
            return false;
        }
        $name = strtolower($player->getName());

        $sql = "SELECT * FROM account WHERE name = :name AND clientId = :clientId AND ip = :ip";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, "Account");
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->execute();

        $this->changePassword($player, $newPassword);

        return true;
    }

    /**
     * パスワードを変更
     * @param Player $player
     * @param string $newPassword
     * @return bool
     */
    public function changePassword(Player $player, string $newPassword) : bool
    {
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET passwordHash = :passwordHash WHERE name = :name";
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt = $this->pdo->prepare($sql);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":name", $name);
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($player));
        /** @noinspection PhpUndefinedMethodInspection */
        $stmt->execute();

        $player->sendMessage("パスワードを設定しました。");

        return true;
    }
}

?>