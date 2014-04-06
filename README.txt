メンテナンス実行スクリプト

------------------------------------------------------
$ php src/maintenance.php
This script set app maintenance. Proceed? (Set (M)aintenance/(U)nset maintenance/e(X)it)
------------------------------------------------------
m を入力するとメンテナンスモード、uを入力するとメンテナンス解除モードでスクリプトが実行されます。
------------------------------------------------------
$ echo m | php src/maintenance.php
------------------------------------------------------
echo と組み合わせて、非対話的に実行できます。

このスクリプトは
  (メンテナスモード)
  1. メンテナンス用のインスタンスをELBに追加する
  2. 指定秒数 Sleepする
  3. プロダクション用のインスタンスをELBから取り外す

  (メンテナス解除モード)
  1. プロダクション用のインスタンスをELBに追加する
  2. 指定秒数 Sleepする
  3. メンテナンス用のインスタンスをELBから取り外す

の2通りの動作をします。

スクリプトの設定
  mv src/config.php.default src/config.php
  edit src/config.php # 'key'と'secret'を設定する
  edit src/maintenance.php # 以下を編集する

---------------------------------------------------
# このスクリプトで操作対象にするインスタンスを識別するためのタグ。
# appName:kawamoto が設定されていないインスタンスはどの操作からも無視される。
$appName = 'kawamoto';

# メンテナンス時に稼働させるインスタンス。instanceTypeタグで設定する。
# 例: instanceType:kawamoto_test_maintenance
$maintenance_tag = 'kawamoto_test_maintenance';

# 通常時に稼働させるインスタンス。instanceTypeタグで設定する。
# 例: instanceType:kawamoto_test_maintenance
$production_tag = 'kawamoto_test_prodction';

# ロードバランサー名
$loadBalancerName = 'kawamoto';

# Sleepする秒数
$time_to_wait = 15;
--------------------------------------------------

AWS上の設定

instanceTypeタグとappNameタグを、スクリプトで指定したとおりにインスタンスに設定してください。
* メンテナンス時にプロダクションにアクセスするためのELBエントリも別に作っておくと、メンテナンス時に動作確認ができます。