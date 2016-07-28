<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase
{
    // データベース
    private $pdo;

    // リスナー
    private $listener;

    // メッセージリソース
    private $messageResource;

    // ログインキャッシュ
    private $loginCache;

    // データベース初期化SQL
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

    // インスタンスを保持
    private static $instance;

    /*
     * インスタンスを取得
     */
    public static function getInstance() : Main
    {
        return self::$instance;
    }

    /*
     * プラグインが有効化されたときのイベント
     */
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

        // プラグインマネージャーに登録してイベントを受信
        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
    }

    /*
     * プラグインが無効化されたときのイベント
     */
    public function onDisable()
    {
        $this->convertToJsonAll();
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        parent::onCommand($sender, $command, $label, $args);

        $this->getLogger()->debug("Main.onCommand: " . $sender->getName() . ", " . $command->getName());

        return false;
    }

    private function convertToJsonAll()
    {
        $stmt = $this->preparedStatement("SELECT * FROM account");
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_CLASS, "Jhelom\\LoginAuth\\Account");

        foreach($results as $account)
        {
            $account->saveToJson();
        }
    }

    /*
     * メッセージリソースをロード
     */
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

    /*
     * メッセージリソースを送信する
     *
     * 単一のメッセージを送信する場合は keys に　string を渡す
     * 複数のメッセージを送信する場合は keys に array を渡す
     */
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

    /*
     * メッセージリソースを取得
     *
     * 引数 args に連想配列を渡すとメッセージの文字列中にプレースフォルダ（波括弧）を置換する
     */
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

    /*
     * データベースに接続
     */
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

    /*
     * セキュリティスタンプマネージャーを取得
     */
    public function getLoginCache() : LoginCache
    {
        return $this->loginCache;
    }

    /*
     * アカウント登録済みなら true を返す
     */
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

    /*
     * 名前をもとにデータベースからアカウントを検索する
     * 不在の場合は isNullフィールドが true のアカウントを返す
     */
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

    /*
     * 端末IDをもとにデータベースからアカウントを検索して、Accountクラスの配列を返す
     * 不在の場合は、空の配列を返す
     */
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

    /*
     * SQLプリペアドステートメント
     */
    public function preparedStatement(string $sql) : \PDOStatement
    {
        return $this->getDatabase()->prepare($sql);
    }

    /*
     * データベースを取得
     */
    private function getDatabase() : \PDO
    {
        return $this->pdo;
    }

    /*
     * 認証済みなら true を返す
     */
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

    /*
     * CommandSender（基底クラス） を Player（派生クラス）に（タイプヒンティングで疑似的で）ダウンキャストする
     */
    public static function castToPlayer($sender) : Player
    {
        return $sender;
    }

    /*
     * パスワードが不適合ならtrueを返す
    */
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

}

?>