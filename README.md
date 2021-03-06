# PocketMine-Plugin / LoginAuth（ログインオース）

ログイン認証を行うプラグインです。

* 高速動作
  * PHP7用に完全に最適化されたオブジェクト指向のコード。
  * 軽量で高速なSQLITE3データベースを採用。
  * ログイン認証の状態管理にオンメ モリキャッシュでデータベースへのアクセス負荷を低減。

* 自動ログイン
  * 最後にログインが成功した時と、名前、IPアドレス、端末が全て同じ場合、ログイン認証をキャッシュして、毎回ログイン操作を行う手間を軽減します。

* １つの端末で登録できるアカウント数を制限可能
  * config.yml 設定ファイルで変更できます。
  * １端末＝１アカウントに制限することも可能です。

* 別の端末からの重複ログインを禁止できます。


# アカウント登録

初めてサーバーに参加するプレイヤーはアカウントを登録する必要があります。

passwordの部分には自分で考えたパスワードを入力します。
パスワードは半角英数字と一部の記号（!#@）のみ使用可能です。

アカウントの登録は次のコマンドを実行します。

```
/register <password>
```


アカウント登録が完了するまで、プレイヤーは移動することもコマンド(register/login以外）を実行することもできません。


# ログイン

プレイヤーがログイン認証するには次のコマンドを実行します。
passwordの部分にはアカウント登録したときのパスワードを入力します。

```
/login <password>
```


ログイン認証にはキャッシュ機能を搭載していて、毎回ログインする手間を軽減しています。
名前、端末、IPをもとに算出したセキュリティスタンプが同一のログインの場合は、ログイン認証は省略されます。

ログイン認証が成功するまで、プレイヤーは移動することもコマンド(register/login以外）を実行することもできません。

# パスワード変更

プレイヤーは自分のパスワードを変更することができます。
セキュリティの観点から定期的にパスワードを変更することが望ましいです。

パスワードを変更するには、ログイン認証済みの状態で次のコマンドを実行します。

```
/password <新しいパスワード>
```

上記コマンドを実行すると、パスワードのタイプミス防止の確認のため、もう一度パスワードの入力が求められます。新しく指定したパスワードをそのまま（スラッシュをつけずに）もう一度入力してください。

パスワードの確認入力が一致すればパスワードの変更は完了です。


# コンソールからアカウント削除

アカウントの削除はサーバーのコンソールからのみ実行できます。

プレイヤーがゲーム中からコマンドでアカウント削除を行うことはできません。
アカウントの削除は、サーバー管理者がコンソールから次のコマンドを実行します。

```
auth unregister <player>
```

# コンソールからアカウント登録

通常アカウントの登録はプレイヤーがゲームに参加した状態でプレイヤー自身が行います。

サーバー管理者がプレイヤーに代わりアカウント登録を代行したい場合などは、サーバーのコンソールからアカウントを登録することができます。

コンソールからアカウントを登録するには、次のコマンドを実行します。
password を指定しない場合は、ランダムなパスワードが自動的に生成されます。

```
auth add <player> [password]
```


# コンソールからアカウント検索

指定したプレイヤー名と同じIPアドレスまたは同じ端末IDのアカウントの検索して一覧表示できます。

```
auth find <プレイヤー名>
```

# コンソールからアカウントのロック解除

アカウントのパスワードがバレたとしても、最終ログイン成功時のIPアドレスと端末IDの両方が違う場合、なりすまし防止のためログインできないようにロックされます。
ロックされた場合は次のコマンドでロックを解除します。


```
auth unlock <player>
```

# 端末毎に登録できるアカウント数の設定

１つの端末に登録できるアカウント数は config.yml の accountSlot で指定します。

  * １つの端末で１つのアカウントしか登録できないように制限したい場合は 1 を指定します。
  * １つの端末を兄弟や家族などの複数のユーザーで使う場合や、サブアカウントを許容する場合は、２以上を指定します。

config.yml
```
accountSlot: 1

```

# パスワードの文字数の設定

パスワードの最小文字数は config.yml の passwordLengthMin に指定します。

```
passwordLengthMin: 5
```

パスワードの最大文字数は config.yml の passwordLengthMax に指定します。

```
passwordLengthMaz: 10
```

# DBファイルの場所を指定

config.yml の dbFile にデータベースファイルの絶対パスを指定することができます。例えば、ポートを変えて複数サーバーを運用していてアカウント情報を共有したい場合に便利です。

```
dbFile: "c:\account.db"
```

下のようにdbFile 項目を空欄にするとプラグインのデータフォルダ（plugins/LoginAuth/account.db)になります。

```
dbFile: ""
```

# 動作環境

* PocketMine（またはGenisysなどの互換サーバー）
* PHP7 (PHP5では動作しません)
* PDO(SQLITE3)モジュール

# ダウンロード

下記URLからpharファイルをダウンロードします。

https://github.com/jhelom/PocketMine-Plugin-LoginAuth/releases

# インストール

pharファイルを pluginsディレクトリ下に配置します。

# PDO(SQLITE)モジュールの導入

PDOについてはPHPの公式ドキュメントを参照してください。
http://php.net/manual/ja/pdo.installation.php

既に PDO(SQLITE3)モジュールが導入されている場合は、この手順は不要です。

Linux の場合や、Windows でも PHP公式サイトからダウンロードしたPHPを使用している場合は、大抵導入済みのはずです。

PocketMine や Genisys のサイトで提供されている　Minecraft PE用にパッケージされた Windows版PHPインストーラーの場合は、PDOモジュールが同梱されていないようです。

PDOモジュールがない場合は、PHP公式サイトからPHPのZIPダウンロードしてください。

http://windows.php.net/download/


* PHPが32ビット版の場合は VC14 x86 Thread Safe の ZIP をダウンロードします。
* PHPが64ビット版の場合は VC14 x64 Thread Safe の ZIP をダウンロードします。

ZIPを展開したら、そのなかから php_pdo_sqlite.dll を、PocketMine用PHPのディレクトリ(php/bin下)にコピーします。

ファイル構成は下記のようになります。
```
Genisys
   +-- bin
        +-- php
             +-- php.exe
             +-- php.ini
             +-- php_pdo_sqlite.dll

```

php.ini に下記行を追記します。
たいていコメントアウトされているので、その場合は先頭のセミコロン(;)を削除します。

```
extension=php_pdo_sqlite.dll
```

以上でPDO(SQLITE3)モジュールの導入は完了です。


# 設定

PocketMine を起動すると pluginsディレクトリ下に「LoginAuth」ディレクトリが自動的に作成されます。

# Special Thanks

本プラグインの開発及びテストにご協力頂いた方々に心より感謝いたします。

Dragon7鯖主 (dragon7.cloudapp.net) 
[https://web.lobi.co/game/minecraftpe/group/0f8e21b8a3daa476600b1dde3815b5f28b542b3c](https://web.lobi.co/game/minecraftpe/group/0f8e21b8a3daa476600b1dde3815b5f28b542b3c)
