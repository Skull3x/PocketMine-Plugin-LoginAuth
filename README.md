# PocketMine-Plugin / LoginAuth（ログインオース）

ログイン認証を行うプラグインです。

* 高速動作
  * PHP7用に完全に最適化されたコード。
  * 軽量で高速なSQLITE3データベースを採用。
  * ログイン認証の状態管理にオンメ モリキャッシュでデータベースへのアクセス負荷を低減。

* 自動ログイン
  * 最後にログインした時と、名前、IPアドレス、端末が全て同じ場合、ログイン認証をキャッシュして、毎回ログイン操作を行う手間を軽減します。

* １つの端末で複数アカウント登録に対応しています。
  * １端末＝１アカウントに制限することも可能です。

* 別の端末からの重複ログインを禁止できます。

# 動作環境

* PocketMine（またはGenisysなどの互換サーバー）
* PHP7 (PHP5では動作しません)
* PDO(SQLITE3)モジュール

# ダウンロード

下記URLからpharファイルをダウンロードします。

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


# アカウント登録

初めてサーバーに参加するプレイヤーはアカウントを登録する必要があります。

passwordの部分には自分で考えたパスワードを入力します。
パスワードは半角英数字と一部の記号（!#@）のみ使用可能です。

アカウントの登録は次のコマンドを実行します。

```
/register <password>
```

# ログイン

ログインするには次のコマンドを実行します。
passwordの部分にはアカウント登録したおきのパスワードを入力します。

```
/login <password>
```


ログイン認証にはキャッシュ機能を搭載していて、毎回ログインする手間を軽減しています。
同一端末＆同一IPからのログインの場合は、ログイン認証は省略されます。



# アカウント削除

アカウントの削除はサーバーのコンソールからのみ実行できます。

一般プレイヤーがアカウントの削除を行うことはできません。
アカウントの削除は、サーバー管理者がコンソールから次のコマンドを実行します。

```
auth unregister <player>
```


# 端末毎に登録できるアカウント数の設定

この機能は１つの端末を兄弟や家族などで使う場合や、サブアカウントを許容する場合を想定しています。

１つの端末に登録できるアカウント数は config.yml の accountSlot で指定します。

また、１つの端末で１つのアカウントしか登録できないように制限したい場合は 1 を指定します。

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