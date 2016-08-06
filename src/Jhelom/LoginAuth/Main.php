<?php

namespace Jhelom\LoginAuth;

use Jhelom\LoginAuth\CommandReceivers\AuthCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\LoginCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\PasswordChangeCommandReceiver;
use Jhelom\LoginAuth\CommandReceivers\RegisterCommandReceiver;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase
{
    // データベース
    private static $instance;

    // リスナー
    private $pdo;

    // メッセージリソース
    private $listener;

    // ログインキャッシュ
    private $messageResource;

    // データベース初期化SQL
    private $loginCache;

    // インスタンスを保持
    private $databaseSchema = <<<_SQL_
CREATE TABLE [account] (
[name] TEXT NOT NULL UNIQUE,
[clientId] TEXT,
[ip] TEXT,
[passwordHash] TEXT NOT NULL,
[securityStamp] TEXT,
[lastLoginTime] TEXT,
PRIMARY KEY(name)
);                
_SQL_;

    /*
     * インスタンスを取得
     */
    private $invoker;

    public static function castToPlayer($sender) : Player
    {
        return $sender;
    }

    public function onEnable()
    {
        $this->getLogger()->info("§a開発者 Jhelom & Dragon7");
        $this->getLogger()->info("§ahttps://github.com/jhelom/PocketMine-Plugin-LoginAuth");

        // Minecraft プラグインのインスタンスは１つだけ
        self::$instance = $this;

        // デフォルト設定をセーブ
        $this->saveDefaultConfig();

        // 設定をリロード
        $this->reloadConfig();

        // メッセージリソースをロード
        $this->loadMessageResource($this->getConfig()->get("locale"));

        // ログインキャッシュを初期化
        $this->loginCache = new LoginCache();

        // データベースに接続
        $this->openDatabase();

        $this->invoker = new CommandInvoker();
        $this->invoker->add(new RegisterCommandReceiver());
        $this->invoker->add(new LoginCommandReceiver());
        $this->invoker->add(new AuthCommandReceiver());
        $this->invoker->add(new PasswordChangeCommandReceiver());

        // プラグインマネージャーに登録してイベントを受信
        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
    }

    private function loadMessageResource(string $locale = NULL)
    {
        // NULLの場合、デフォルトの言語にする
        $locale = $locale ?? "ja";

        // 言語の指定をもとにファイルのパスを組み立て
        $file = "messages-" . $locale . ".yml";
        $path = $this->getDataFolder() . $file;

        // ファイルが不在なら
        if (!file_exists($path)) {
            // 日本語ファイルのパスにする
            $file = "messages-ja.yml";
            $path = $this->getDataFolder() . $file;
        }

        // リソースをセーブ（上書き）
        $this->saveResource($file, true);

        // リソースをロード
        $this->messageResource = new Config($path, Config::YAML);
    }

    private function openDatabase()
    {
        $path = $this->getConfig()->get("dbFile");

        // config で path が指定されていない場合
        if ($path == NULL && $path == "") {
            // データベースファイルのパスを組み立て
            $path = rtrim($this->getDataFolder(), "/") . DIRECTORY_SEPARATOR . "account.db";
        }

        $this->getLogger()->debug("DB = " . $path);

        // データベースファイルが不在なら、初期化フラグを立てる
        $isInitializing = !file_exists($path);

        // 接続文字列を組み立て
        $connectionString = "sqlite:" . $path;

        // データベースを開く
        $this->pdo = new \PDO($connectionString);

        // SQLエラーで例外をスローするように設定
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 初期化フラグが立っていたら
        if ($isInitializing) {
            // テーブルを作成
            $this->pdo->exec($this->databaseSchema);
        }
    }

    public function onDisable()
    {
        $this->convertToJsonAll();
    }

    private function convertToJsonAll()
    {
        $stmt = $this->preparedStatement("SELECT * FROM account");
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_CLASS, "Jhelom\\LoginAuth\\Account");

        foreach ($results as $account) {
            $account->saveToJson();
        }
    }

    public function preparedStatement(string $sql) : \PDOStatement
    {
        return $this->getDatabase()->prepare($sql);
    }

    private function getDatabase() : \PDO
    {
        return $this->pdo;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        return $this->getInvoker()->execute($sender, $command->getName(), $args);
    }

    public function getInvoker() : CommandInvoker
    {
        return $this->invoker;
    }

    function isRegistered(Player $player) : bool
    {
        // アカウントを検索
        $account = $this->findAccountByName($player->getName());

        if ($account->isNull) {
            return false;
        } else {
            return true;
        }
    }

    public function findAccountByName(string $name) : Account
    {
        $sql = "SELECT * FROM account WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($name), \PDO::PARAM_STR);
        $stmt->execute();

        // データベースからクラスとして取得
        $account = $stmt->fetchObject("Jhelom\\LoginAuth\\Account");

        // 検索結果が０件の場合は false なので
        if ($account === false) {
            // isNull が true の Account を返す
            return new Account(true);
        }

        // データベースから取得したクラスを返す
        return $account;
    }

    public function findAccountListByClientId(string $clientId) : array
    {
        $sql = "SELECT * FROM account WHERE clientId = :clientId ORDER BY name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":clientId", $clientId, \PDO::PARAM_STR);
        $stmt->execute();

        // データベースからクラスの配列として取得
        $results = $stmt->fetchAll(\PDO::FETCH_CLASS, "Jhelom\\LoginAuth\\Account");

        return $results;
    }

    public function isAuthenticated(Player $player) :bool
    {
        // キャッシュを検証
        if ($this->getLoginCache()->validate($player)) {
            // 認証済みを示す true を返す
            return true;
        }

        // 名前をもとにアカウントをデータベースから検索
        $account = $this->findAccountByName(strtolower($player->getName()));

        // アカウントがアカウントが存在しない
        if ($account->isNull) {
            return false;
        }

        // データベースのセキュリティスタンプと比較して違っている
        if ($account->securityStamp !== Account::makeSecurityStamp($player)) {
            return false;
        }

        // キャッシュに登録
        $this->getLoginCache()->add($player);

        return true;
    }

    public function getLoginCache() : LoginCache
    {
        return $this->loginCache;
    }

    public function isInvalidPassword(CommandSender $sender, string $password, string $usage = NULL) : bool
    {
        // パスワードが空欄の場合
        if ($password === "") {
            Main::getInstance()->sendMessageResource($sender, "passwordRequired");
            if ($usage !== NULL) {
                Main::getInstance()->sendMessageResource($sender, $usage);
            }
            return true;
        }

        // 使用可能文字の検証
        if (!preg_match("/^[a-zA-Z0-9!#@]+$/", $password)) {
            Main::getInstance()->sendMessageResource($sender, "passwordFormat");
            return true;
        }

        // 設定ファイルからパスワードの文字数の下限を取得
        $passwordLengthMin = Main::getInstance()->getConfig()->get("passwordLengthMin");

        // 設定ファイルからパスワードの文字数の上限を取得
        $passwordLengthMax = Main::getInstance()->getConfig()->get("passwordLengthMax");

        // パスワードの文字数を取得
        $passwordLength = strlen($password);

        // パスワードが短い場合
        if ($passwordLength < $passwordLengthMin) {
            Main::getInstance()->sendMessageResource($sender, "passwordLengthMin", ["length" => $passwordLengthMin]);
            return true;
        }

        // パスワードが長い場合
        if ($passwordLength > $passwordLengthMax) {
            Main::getInstance()->sendMessageResource($sender, "passwordLengthMax", ["length" => $passwordLengthMax]);
            return true;
        }

        return false;
    }

    public function sendMessageResource(CommandSender $sender, $keys, array $args = NULL)
    {
        // keys が配列ではない場合
        if (!is_array($keys)) {
            // 配列に変換
            $keys = [$keys];
        }

        // keys をループ
        foreach ($keys as $key) {
            $sender->sendMessage($this->getMessage($key, $args));
        }
    }

    public function getMessage(string $key, array $args = NULL) : string
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $message = $this->messageResource->get($key);

        if ($message == NULL || $message == "") {
            $this->getLogger()->warning("メッセージリソース不在: " . $key);
            $message = $key;
        }

        // args が配列の場合
        if (is_array($args)) {
            // 配列をループ
            foreach ($args as $key => $value) {
                // プレースフォルダを組み立て
                $placeHolder = "{" . $key . "}";

                // プレースフォルダをバリューで置換
                $message = str_replace($placeHolder, $value, $message);
            }
        }

        return $message;
    }

    public static function getInstance() : Main
    {
        return self::$instance;
    }
}

?>