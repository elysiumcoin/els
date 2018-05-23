<!--?php /* Template name: BlockChain */ ?-->
<?php
/*
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)
    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>
    https://github.com/stolendata/rpc-ace/
    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
    Hosted & Adapter by: Astat ORG 
*/

const ACE_VERSION = '0.8.0';
const RPC_HOST = '127.0.0.1';
const RPC_PORT = 10458;
const RPC_USER = 'user';
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

class RPCAce
{
    private static $block_fields = [ 'hash', 'nextblockhash', 'previousblockhash', 'confirmations', 'size', 'height', 'version', 'merkleroot', 'time', 'nonce', 'bits', 'difficulty', 'mint', 'proofhash' ];
    private static function base()
    {
        $rpc = new Bitcoin( RPC_USER, RPC_PASS, RPC_HOST, RPC_PORT );
        
        $info = $rpc->getinfo();   
      
        if( $rpc->status !== 200 && $rpc->error !== '' )
            return [ 'err'=>'failed to connect - node not reachable, or user/pass incorrect' ];
        if( DB_FILE )
        {
            $pdo = new PDO( 'sqlite:' . DB_FILE );
            $pdo->exec( 'create table if not exists block ( height int, hash char(64), json blob );
                         create table if not exists tx ( txid char(64), json blob );
                         create unique index if not exists ub on block ( height );
                         create unique index if not exists uh on block ( hash );
                         create unique index if not exists ut on tx ( txid );' );
        }
        $output['rpcace_version'] = ACE_VERSION;
        $output['coin_name'] = COIN_NAME;
        $output['num_blocks'] = $info['blocks'];
        $output['num_connections'] = $info['connections'];
        if( COIN_POS === true )
        {
            $output['current_difficulty_pow'] = $info['difficulty']['proof-of-work'];
            $output['current_difficulty_pos'] = $info['difficulty']['proof-of-stake'];
        }
        else
            $output['current_difficulty_pow'] = $info['difficulty'];
        if( !($hashRate = @$rpc->getmininginfo()['netmhashps']) && !($hashRate = @$rpc->getmininginfo()['networkhashps'] / 1000000) )
            $hashRate = $rpc->getnetworkhashps() / 1000000;
        $output['hashrate_mhps'] = sprintf( '%.2f', $hashRate );
        return [ 'output'=>$output, 'rpc'=>$rpc, 'pdo'=>@$pdo ];
    }
    private static function block( $base, $b )
    {
        if( DB_FILE )
        {
            $sth = $base['pdo']->prepare( 'select json from block where height = ? or hash = ?;' );
            $sth->execute( [$b, $b] );
            $block = $sth->fetchColumn();
            if( $block )
                $block = json_decode( gzinflate($block), true );
        }
        if( @$block == false )
        {
            if( strlen($b) < 64 )
                $b = $base['rpc']->getblockhash( $b );
            $block = $base['rpc']->getblock( $b );
        }
        if( DB_FILE && @$block )
        {
            $sth = $base['pdo']->prepare( 'insert into block values (?, ?, ?);' );
            $sth->execute( [$block['height'], $block['hash'], gzdeflate(json_encode($block))] );
        }
        return $block ? $block : false;
    }
    private static function tx( $base, $txid )
    {
        if( DB_FILE )
        {
            $sth = $base['pdo']->prepare( 'select json from tx where txid = ?;' );
            $sth->execute( [$txid] );
            $tx = $sth->fetchColumn();
            if( $tx )
                $tx = json_decode( gzinflate($tx), true );
        }
        if( @$tx == false )
            $tx = $base['rpc']->getrawtransaction( $txid, 1 );
        if( DB_FILE && @$tx )
        {
            $sth = $base['pdo']->prepare( 'insert into tx values (?, ?);' );
            $sth->execute( [$txid, gzdeflate(json_encode($tx))] );
        }
        return $tx ? $tx : false;
    }
    // enumerate block details from hash
    public static function get_block( $hash )
    {
        if( preg_match('/^[0-9a-f]{64}$/i', $hash) !== 1 )
            return RETURN_JSON ? json_encode( ['err'=>'not a valid block hash'] ) : [ 'err'=>'not a valid block hash' ];
        $base = self::base();
        if( isset($base['err']) )
            return RETURN_JSON ? json_encode( $base ) : $base;
        if( ($block = self::block($base, $hash)) === false )
            return RETURN_JSON ? json_encode( ['err'=>'no block with that hash'] ) : [ 'err'=>'no block with that hash' ];
        $total = 0;
        foreach( $block as $id => $val )
            if( $id === 'tx' )
                foreach( $val as $txid )
                {
                    $transaction['id'] = $txid;
                    if( ($tx = self::tx($base, $txid)) === false )
                        continue;
                    if( isset($tx['vin'][0]['coinbase']) )
                        $transaction['coinbase'] = true;
                    foreach( $tx['vout'] as $entry )
                        if( $entry['value'] > 0.0 )
                        {
                            // nasty number formatting trick that hurts my soul, but it has to be done...
                            $total += ( $transaction['outputs'][$entry['n']]['value'] = rtrim(rtrim(sprintf('%.8f', $entry['value']), '0'), '.') );
                            $transaction['outputs'][$entry['n']]['address'] = $entry['scriptPubKey']['addresses'][0];
                        }
                    $base['output']['transactions'][] = $transaction;
                    $transaction = null;
                }
            elseif( in_array($id, self::$block_fields) )
                $base['output']['fields'][$id] = $val;
        $base['output']['total_out'] = $total;
        $base['rpc'] = null;
        return RETURN_JSON ? json_encode( $base['output'] ) : $base['output'];
    }
    // create summarized list from block number
    public static function get_blocklist( $ofs, $n = BLOCKS_PER_LIST )
    {
        $base = self::base();
        if( isset($base['err']) )
            return RETURN_JSON ? json_encode( $base ) : $base;
        $offset = $ofs === null ? $base['output']['num_blocks'] : abs( (int)$ofs );
        if( $offset > $base['output']['num_blocks'] )
            return RETURN_JSON ? json_encode( ['err'=>'block does not exist'] ) : [ 'err'=>'block does not exist' ];
        $i = $offset;
        while( $i >= 0 && $n-- )
        {
            $block = self::block( $base, $i );
            $frame['hash'] = $block['hash'];
            $frame['height'] = $block['height'];
            $frame['difficulty'] = $block['difficulty'];
            $frame['time'] = $block['time'];
            $frame['date'] = gmdate( DATE_FORMAT, $block['time'] );
            $txCount = 0;
            $valueOut = 0;
            foreach( $block['tx'] as $txid )
            {
                $txCount++;
                if( ($tx = self::tx($base, $txid)) === false )
                    continue;
                foreach( $tx['vout'] as $vout )
                    $valueOut += $vout['value'];
            }
            $frame['tx_count'] = $txCount;
            $frame['total_out'] = $valueOut;
            $base['output']['blocks'][] = $frame;
            $frame = null;
            $i--;
        }
        $base['rpc'] = null;
        return RETURN_JSON ? json_encode( $base['output'] ) : $base['output'];
    }
}


$ace = RPCAce::get_blocklist( $query, 1 );  
$query = substr( @$_SERVER['QUERY_STRING'], 0, 64 );

echo <<<END
<!DOCTYPE html>
<!--
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)
    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>
    https://github.com/stolendata/rpc-ace/
    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
-->
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="robots" content="index,nofollow,nocache" />
<meta name="author" content="Robin Leffmann (djinn at stolendata dot net)" />
END;
if( empty($query) || ctype_digit($query) )
    echo '<meta http-equiv="refresh" content="' . REFRESH_TIME . '; url=' . basename( __FILE__ ) . "\" />\n";
echo '<title>' . COIN_NAME . ' block explorer &middot; RPC Ace v' . ACE_VERSION . "</title>\n";
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
input {color: black;}
a { color: #f6f6f6; }
div.mid { width: 90%;
          margin: 2% auto; }
td { width: 16%; }
td.urgh { width: 100%; }
td.key { text-align: right; }
td.value { padding-left: 16px; width: 100%; }
tr.illu:hover { background-color: #303030; }
</style>

<script>
  urlid = 'checkelstransaction.php?';
  fieldid = 'hash';
</script>

</head>
<body>
<div class="mid" style="position:relative;margin-left:40px;top:-70px;";>
END;
// header

$imgurl='img/connect3_16.png';

if ($ace['num_connections'] < 1 ) {
   $imgurl='img/connect0_16.png';
}

if (($ace['num_connections'] > 0) && ($ace['num_connections'] < 3)) {
   $imgurl='img/connect1_16.png';
}

if (($ace['num_connections'] > 2) && ($ace['num_connections'] < 5)) {
   $imgurl='img/connect2_16.png';
}

if (($ace['num_connections'] > 4) && ($ace['num_connections'] < 7)) {
   $imgurl='img/connect3_16.png';
}

if ($ace['num_connections'] > 6) {
   $imgurl='img/connect4_16.png';
}

echo '<table><tr><td class="urgh"><div width="300">
<a href="' . COIN_HOME . '" title="'. COIN_NAME . ' Home"><img src="img/elysium-logo.jpg" align="left" style="height:56px; width:56px; border-radius: 50%; margin-bottom:20px; margin-right: 30px;margin-left: 0; display:inline;box-shadow: -1px -1px 8px 10px rgba(0,255,0,0.5);" alt="logo" title="Logo of Elysium Coin" /></a>
<b><a href="elsexplorer.php">' . COIN_NAME . '</a></b> block explorer</td><td>Blocks:</td><td><a href="?' . $ace['num_blocks'] . '">' . $ace['num_blocks'] . '</a></div>';
$diffNom = 'Difficulty';
$diff = sprintf( '%.3f', $ace['current_difficulty_pow'] );
if( COIN_POS )
{
    $diffNom .= ' &middot; PoS';
    $diff .= ' &middot;' . sprintf( '%.1f', $ace['current_difficulty_pos'] );
}

echo "<tr><td><input id='hash' placeholder='Type TX ID' style='width:80%' name='hash' type='text'><input type='submit' onclick='window.location.assign(urlid+document.getElementById(fieldid).value);'></td><td>$diffNom:</td><td>$diff</td></tr>";
echo '<tr><td></td><td>Network hashrate: </td><td>' . $ace['hashrate_mhps'] . ' MH/s</td></tr><tr><td><a href="elsexplorer.php" style="color:#00FF00;">Block Explorer</a></td><td>Connections: </td><td>'.$ace['num_connections'].'<img style="margin-left: 10px;" src="'.$imgurl.'"></td></tr></table><hr style="color: #3e3e3e; background-color: #3e3e3e; height: 3px; border: 0;">';
// list of blocks

if (!empty($query)) {

$remote = new Bitcoin(RPC_USER, RPC_PASS, RPC_HOST, RPC_PORT);
$txhash = $query;
echo "<b>TXID: ".$txhash."<b><br><br>";
echo "<table>";
echo "<thead>";
echo "<tr>";
echo "<th align='left'>Tx/Value</th>";
echo "<th align='left'>To</th>";
echo "</tr>";
echo "</thead>";

$tx = $remote->getrawtransaction($txhash, 1);
if (!$tx) {
    continue;
}
$valuetx = 0;
foreach ($tx['vout'] as $vout) {
    $valuetx += $vout['value'];
}
$valuetx = number_format($valuetx,8);
echo "<tr class='ssrow'>";
echo "<td><span style='font-family: monospace;'>{$tx['txid']}</span><br>Value: {$valuetx} ===> Confirmations: {$tx['confirmations']}</td>";
echo "<td>";
foreach ($tx['vout'] as $vout) {
    $value = number_format($vout['value'],8);
    if (isset($vout['scriptPubKey']['addresses'][0])) {
        echo "<span style='font-family: monospace;'>{$vout['scriptPubKey']['addresses'][0]}</span><br>Value: {$value}<br>";
    } else {
        echo "Value: {$value}<br>";
    }
    echo '<br>';
}
echo "</td>";
echo "</tr>";
echo "</table>";

}
?>
<br><br><br><br>
<center><a href="http://elysiumcoin.org/" style="bottom: 20px;font-size:12px;position:relative;bottom: -12px;">Block Explorer Hosted by Elysium Coin ORG</a><br><br></cemter></body></html>

</body></html>

