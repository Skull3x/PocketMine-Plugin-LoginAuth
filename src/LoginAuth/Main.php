<?php

namespace LoginAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

require_once("Account.php");

class Main extends PluginBase
{
    // データベース
    private $pdo;

    // リスナー
    private $listener;

    // メッセージリソース
    private $messageResource;

    // セキュリティスタンプマネージャー
    private $securityStampManager;

    // データベース初期化SQL
    private $ddl = <<<_SQL_
CREATE TABLE [account] (
[name] TEXT NOT NULL UNIQUE,
[clientId] TEXT NOT NULL,
[ip] TEXT NOT NULL,
[passwordHash] TEXT NOT NULL,
[securityStamp] TEXT NOT NULL,
[isDeleted] INTEGER DEFAULT 0,
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
        $this->loadMessageResource("ja");

        // セキュリティスタンプマネージャーを初期化
        $this->securityStampManager = new SecurityStampManager();

        // データベースに接続
        $this->openDatabase();

        // プラグインマネージャーに登録してイベントを受信
        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
    }

    /**
     * プラグインが無効化されたときのイベント
     */
    public function onDisable()
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        $this->getLogger()->debug("onCommand: " . $sender->getName() . ": " . $command->getName());
    }

    /**
     * メッセージリソースをロード
     *
     * @param string $locale
     */
    private function loadMessageResource(string $locale)
    {
        // 言語の指定をもとにファイルのパスを組み立て
        $file = "messages-" . $locale . ".yml";
        $path = $this->getDataFolder() . $file;

        // ファイルが不在なら
        if (!file_exists($path)) {
            // 日本語ファイルのパスにする
            $file = "messages-ja.yml";
            $path = $this->getDataFolder() . $file;
        }

        // リソースをセーブ
        $this->saveResource($file);

        // リソースをロード
        $this->messageResource = new Config($path, Config::YAML);
    }

    /**
     * メッセージを取得
     *
     * メッセージの文字列中にプレースフォルダ（波括弧）を置換する場合は、引数 args に連想配列を渡す
     *
     * @param string $key
     * @param array|NULL $args
     * @return string
     */
    public function getMessage(string $key, array $args = NULL) : string
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $message = $this->messageResource->get($key) ?? "";

        if (is_array($args)) {
            foreach ($args as $key => $value) {
                $message = str_replace("{" . $key . "}", $value, $message);
            }
        }

        return $message;
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
     * セキュリティスタンプマネージャーを取得
     *
     * @return SecurityStampManager
     */
    public function getSecurityStampManager() : SecurityStampManager
    {
        return $this->securityStampManager;
    }

    /**
     * アカウント登録が可能か検証する（データベースへの登録は行わない）
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function tryRegister(Player $player, string $password) : bool
    {
        // 既にログイン認証済みなら
        if ($this->isAuthenticated($player)) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("registerAlready"));
            return false;
        }

        $account = $this->findAccountByName($player->getName());

        // データベースに同じ名前のアカウントが既に存在する場合
        if (!$account->isNull) {
            // 削除フラグが立っていれば
            if ($account->isDeleted) {
                // 過去に登録されていたとメッセージを表示
                $player->sendMessage(TextFormat::RED . $this->getMessage("alreadyExistsNameDeleted", ["name" => $player->getName()]));
            } else {
                // 登録されているとメッセージを表示
                $player->sendMessage(TextFormat::RED . $this->getMessage("alreadyExistsName", ["name" => $player->getName()]));
            }

            return false;
        }

        // パスワードを検証
        if (!$this->validatePassword($player, $password, $this->getMessage("passwordRequired"))) {
            return false;
        }

        return true;
    }

    /**
     * アカウントをデータベースに登録する
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function register(Player $player, string $password) :bool
    {
        // 認証済みなら
        if ($this->isAuthenticated($player)) {
            $player->sendMessage(TextFormat::GREEN . $this->getMessage("alreadyLogin"));
            return false;
        }

        // パスワード検証
        if (!$this->validatePassword($player, $password, $this->getMessage("passwordRequired"))) {
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

            $player->sendMessage(TextFormat::RED . $this->getMessage("accountSlotOver1", ["accountSlot" => $accountSlot]));
            $player->sendMessage(TextFormat::RED . $this->getMessage("accountSlotOver2"));
            $player->sendMessage(TextFormat::RED . $nameListStr);

            return false;
        }

        // 名前を小文字に変換
        $name = strtolower($player->getName());

        // 名前でデータベースからアカウントを検索
        $account = $this->findAccountByName($name);

        // データベースに同じ名前のアカウントが既に存在する場合
        if (!$account->isNull) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("alreadyExistsName", ["name" => $player->getName()]));
            return false;
        }

        //　データベースに登録
        $sql = "INSERT INTO account (name, clientId, ip, passwordHash, securityStamp) VALUES (:name, :clientId, :ip, :passwordHash, :securityStamp)";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":clientId", $player->getClientId(), \PDO::PARAM_STR);
        $stmt->bindValue(":ip", $player->getAddress(), \PDO::PARAM_STR);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($password), \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $this->getSecurityStampManager()->makeStamp($player), \PDO::PARAM_STR);
        $stmt->execute();

        $this->getSecurityStampManager()->add($player);

        // メッセージ表示
        $player->sendMessage(TextFormat::GREEN . $this->getMessage("registerSuccessful"));

        return true;
    }

    /**
     * ログインする
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function login(Player $player, string $password):bool
    {
        // 名前をもとにデータベースからアカウントを検索する
        $account = $this->findAccountByName($player->getName());

        // アカウントが不在なら
        if ($account->isNull) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("register"));
            return false;
        }

        // アカウントの削除フラグが立っていたら
        if ($account->isDeleted) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("accountDeleted", ["name" => $player->getName()]));
            return false;
        }

        // 空白文字を除去
        $password = trim($password);

        // パスワードを検証
        if (!$this->validatePassword($player, $password, $this->getMessage("passwordRequired"))) {
            // 検証失敗ならリターン
            return false;
        }

        // パスワードハッシュを生成
        $passwordHash = $this->makePasswordHash($password);

        // パスワードハッシュを比較
        if ($account->passwordHash != $passwordHash) {
            // パスワード不一致メッセージを表示してリターン
            $player->sendMessage(TextFormat::RED . $this->getMessage("passwordError"));
            return false;
        }

        // データベースのセキュリティスタンプを更新
        $securityStamp = $this->getSecurityStampManager()->makeStamp($player);
        $name = strtolower($player->getName());

        $sql = "UPDATE account SET securityStamp = :securityStamp WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name, \PDO::PARAM_STR);
        $stmt->bindValue(":securityStamp", $securityStamp, \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーに登録
        $this->getSecurityStampManager()->add($player);

        // ログイン成功メッセージを表示
        $player->sendMessage(TextFormat::GREEN . $this->getMessage("loginSuccessful"));

        // 正常終了を示す true を返す
        return true;
    }

    /**
     * アカウント登録済みなら true を返す
     * @param Player $player
     * @return bool
     */
    function isRegistered(Player $player) : bool
    {
        $account = $this->findAccountByName($player->getName());

        if ($account->isNull) {
            return false;
        }

        if ($account->isDeleted) {
            return false;
        }

        return true;
    }

    /**
     * 名前をもとにデータベースからアカウントを検索する
     * 不在の場合は isNullフィールドが true のアカウントを返す
     *
     * @param string $name
     * @return account
     */
    public function findAccountByName(string $name) : Account
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
     *
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
     * 認証済みなら true を返す
     *
     * @param Player $player
     * @return bool
     */
    public function isAuthenticated(Player $player) :bool
    {
        // キャッシュを検証
        if ($this->getSecurityStampManager()->validate($player)) {
            // 認証済みを示す true を返す
            return true;
        }

        // 名前をもとにアカウントをデータベースから検索
        $account = $this->findAccountByName(strtolower($player->getName()));

        // アカウントがアカウントが存在しない
        if ($account->isNull) {
            return false;
        }

        // 削除フラグが立っている
        if ($account->isDeleted) {
            return false;
        }

        // データベースのセキュリティスタンプと比較して違っている
        if ($account->securityStamp !== $this->getSecurityStampManager()->makeStamp($player)) {
            return false;
        }

        // キャッシュに登録
        $this->getSecurityStampManager()->add($player);
        return true;
    }

    /**
     * パスワードを検証、成功なら true、失敗なら false を返す
     *
     * @param Player $player
     * @param string $password
     * @param string $emptyErrorMessage
     * @return bool
     */
    public function validatePassword(Player $player, string $password, string $emptyErrorMessage) : bool
    {
        // パスワードが空欄の場合
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
            $player->sendMessage(TextFormat::RED . $this->getMessage("passwordLengthMin", ["length" => $passwordLengthMin]));
            return false;
        }

        // パスワードが長い場合
        if ($passwordLength > $passwordLengthMax) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("passwordLengthMax", ["length" => $passwordLengthMax]));
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
        $sql = "SELECT * FROM account WHERE clientId = :clientId AND isDeleted == 0 ORDER BY name";
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
    public function makePasswordHash(string $password) : string
    {
        return hash("sha256", $password);
    }

    /**
     * アカウント削除が可能か検証する（データベースへの反映は行わない）
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function tryUnregister(Player $player, string $password) : bool
    {
        if (!$this->validatePassword($player, $password, $this->getMessage("unregisterPasswordRequired"))) {
            return false;
        }

        $account = $this->findAccountByName($player->getName());

        if ($account->isNull) {
            $this->getLogger()->warning("dispatchUnregister: " . $player->getName() . "のアカウントが存在しない");
            return false;
        }

        if ($account->passwordHash !== $this->makePasswordHash($password)) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("unregisterPasswordError"));
            return false;
        }

        return true;
    }

    /**
     * アカウントを削除する
     *
     * 同じ名前を再利用できないようにするため、
     * 実際にはデータベースからレコードを物理削除するのではなく、
     * isDeleted　カラムを 1 にすることで論理削除として扱う。
     *
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function unregister(Player $player, string $password) :bool
    {
        // パスワードが空欄の場合
        if ($password === "") {
            $player->sendMessage(TextFormat::RED . $this->getMessage("unregisterPasswordRequired"));
            return false;
        }

        $account = $this->findAccountByName($player->getName());

        // アカウントが不在の場合
        if ($account->isNull) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("unregisterNotFound"));
            return false;
        }

        $passwordHash = $this->makePasswordHash($password);

        // パスワードが違う場合
        if ($account->passwordHash !== $passwordHash) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("unregisterPasswordError"));
            return false;
        }

        // データベースの削除フラグを１に更新
        $sql = "UPDATE account SET isDeleted = 1 WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($player->getName()), \PDO::PARAM_STR);
        $stmt->execute();

        // セキュリティスタンプマネージャーから削除
        $this->getSecurityStampManager()->remove($player);

        // プレイヤーを強制ログアウト
        $player->close("", $this->getMessage("unregisterSuccessful"));

        return true;
    }

    /**
     * パスワード変更が可能か検証する
     *
     * データベースへの反映は行わない
     *
     * @param Player $player
     * @param string $newPassword
     * @return bool
     */
    public function tryChangePassword(Player $player, string $newPassword) : bool
    {
        if (!$this->validatePassword($player, $newPassword, $this->getMessage("passwordRequired"))) {
            return false;
        }

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

        if (!$this->validatePassword($player, $newPassword, $this->getMessage("passwordChangeRequired"))) {
            return false;
        }

        $name = strtolower($player->getName());

        $sql = "UPDATE account SET passwordHash = :passwordHash WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":passwordHash", $this->makePasswordHash($newPassword));
        $stmt->execute();

        $player->sendMessage(TextFormat::GREEN . $this->getMessage("passwordChangeSuccessful"));

        return true;
    }
}

?>