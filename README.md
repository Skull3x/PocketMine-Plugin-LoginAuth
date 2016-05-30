# PocketMine-Plugin / LoginAuth

# 動作環境

* PocketMine
* PHP7 (PHP5では動作しません)
* PDOモジュール(SQLITE3)

# ダウンロード

下記URLからpharファイルをダウンロードします。

# インストール

pharファイルを pluginsディレクトリ下に配置します。

# PDO(SQLITE)モジュールの導入

既に PDO(SQLITE3)モジュールが導入されている場合は、この手順は不要です。

PDOについてはPHPの公式ドキュメントを参照してください。

http://php.net/manual/ja/pdo.installation.php

Windowsの場合

php_pdo_sqlite.dll が必要です。

PHPが32ビット版の場合は VC14 x86 Thread Safe の ZIP をダウンロードします。

PHPが64ビット版の場合は VC14 x64 Thread Safe の ZIP をダウンロードします。
ZIPを展開して php_pdo_sqlite.dll を PocketMine用PHPのディレクトリ(php/bin下)にコピーします。

下記のような構成します。

Genisys
   +-- bin
       +-- php
            +-- php.exe
            +-- php.ini
            +-- php_pdo_sqlite.dll
            

php.ini に下記行を追記します。
たいていコメントアウトされているので、その場合は先頭のセミコロン(;)を削除します。

extension=php_pdo_sqlite.dll


# 設定

PocketMine を起動すると pluginsディレクトリ下に「LoginAuth」ディレクトリが自動的に作成されます。

# 仕様

* １つの端末で複数のプレーヤー名の使い分けができる。
* 別の端末からの重複ログイン禁止

