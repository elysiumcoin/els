<!--?php /* Template name: BlockChain */ ?-->
<?php
/*
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)
    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>
    https://github.com/stolendata/rpc-ace/
    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
*/
const ACE_VERSION = '0.8.0';
const RPC_HOST1 = '127.0.0.1';
const RPC_PORT = 10458;
const RPC_USER = 'username';
const RPC_PASS = 'password';
const COIN_NAME = 'ELYSIUM';
const COIN_POS = false;
const RETURN_JSON = false;
const DATE_FORMAT = 'Y-M-d H:i:s';
const BLOCKS_PER_LIST = 12;
//const DB_FILE = 'db/astato_db.sq3';
const DB_FILE = false;
// for the example explorer
const COIN_HOME = 'http://www.elysiumcoin.org/';
const REFRESH_TIME = 180;
// courtesy of https://github.com/aceat64/EasyBitcoin-PHP/
require_once( 'easybitcoin.php' );

?>


<!DOCTYPE html>
<!--
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)
    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>
    https://github.com/stolendata/rpc-ace/
    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
    Hosted & Adapter by: Astato ORG
-->
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="robots" content="index,nofollow,nocache" />
<meta name="author" content="Robin Leffmann (djinn at stolendata dot net)" />
<?php
echo '<meta http-equiv="refresh" content="' . REFRESH_TIME . '; url=' . basename( __FILE__ ) . "\" />\n";
echo '<title>' . COIN_NAME . ' block explorer - Active Nodes List - IP and Versions</title>';
echo '<meta name="description" content="Elysium active list nodes and core versions for connections on Elysium addnodes config"/>';
echo <<<END
<link href="https://fonts.googleapis.com/css?family=Varela" rel="stylesheet" type="text/css">
<style type="text/css">
html { height: 100%;
       background-color: #002000;
       background-attachment: fixed;
       color: #f6f6f6;
       font-family: Varela, sans-serif;
       font-size: 17px;
       white-space: pre; }
a { color: #f6f6f6; text-decoration:none;}

h1 {font-size: 36px; display:inline;margin: 50px;padding:20px;}

div.mid { width: 90%;
          margin: 2% auto; }
td { width: 16%; }
td.urgh { width: 100%; }
td.key { text-align: right; }
td.value { padding-left: 16px; width: 100%; }
tr.illu:hover { background-color: #303030; }
</style>

<script>
  urlid = 'elysiumnodes.php?';
  fieldid = 'hash';
</script>

</head>
<body>
<a href="http://www.elysiumcoin.org" title="Elysium nodes list" style="text-shadow: 1px 1px rgba(0,0,255,0.5);"><img src="img/elysium-logo.jpg" style="height:75px; width:75px; border-radius: 50%; margin-left: 50px; box-shadow: -1px -1px 8px 10px rgba(0,255,0,0.5);" alt="logo" title="Logo of Elysium Coin" /><h1 style="position:absolute;top:0;text-shadow: 1px 1px 10px rgba(0,255,0,0.8);">Elysium Coin Active Nodes</h1></a><hr style="margin-top: 15px;">
<div class="mid">
END;
// header
echo '<table><tr><td class="urgh"><b><a href="elsexplorer.php" title="Elysium Active Nodes">' . COIN_NAME . '</a></b> Explorer (Nodes)</td><td></td></tr></table><br><br>';
// list of peers


        //echo print_r($info);

$rpc = new Bitcoin( RPC_USER, RPC_PASS, RPC_HOST1, RPC_PORT );
$info = $rpc->getpeerinfo();


if( $rpc->status !== 200  && $rpc->error !== '' ) {
  echo 'Local Node Off Line<br><br><br>';
} else {

echo '<table><tr><td>IP Node</td><td>Port</td><td>Since</td><td>Version</td><td>SubVersion</td></tr>';
foreach ($info as $key) {
    echo '<tr><td>'.str_replace(':','</td><td>',$key['addr']).'</td><td>'.gmdate( DATE_FORMAT, $key['conntime'] ).'</td><td>'.$key['version'].'</td><td>'.$key['subver'].'</td></tr>';
}
  echo '</table><br><br>';
}




echo '</div><hr><center><a href="http://elysiumcoin.org/" style="bottom: 20px;font-size:12px;position:relative;bottom: -12px;">Block Explorer Hosted by Elysium Coin ORG</a><br><br></cemter></body></html>';
?>
