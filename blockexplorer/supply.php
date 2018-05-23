<?php
/*
    RPC Ace v0.8.0 (RPC AnyCoin Explorer)
    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>
    https://github.com/stolendata/rpc-ace/
    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/
    Hosted & Adapter by: Astato ORG
*/
const ACE_VERSION = '0.8.0';
const RPC_HOST = '127.0.0.1';
const RPC_PORT = 10458;
const RPC_USER = 'usename';
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
        if( $rpc->status !== 200 && $rpc->error !== '' ) {
		    //header("Location: https://astato.org/blockchain2.php");
			die();
        }
            //return [ 'err'=>'Failed to connect node001, try node002 <a href="https://astato.org/blockchain2.php"><b>Go to Block Explorer 002</b></a>' ];
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

function get_n_coins($blocks) {
  $n = floor($blocks/120000);  
  $coins = 0;
  for ($i=0;$i<$n;$i++) {
    $coins = $coins+(120000*(60/pow(2,$i)));
    $blocks = $blocks-120000;
  }
  $coins = $coins+($blocks*(60/pow(2,$i)));
  return $coins;
}

$query = substr( @$_SERVER['QUERY_STRING'], 0, 64 );
if( strlen($query) == 64 )
    $ace = RPCAce::get_block( $query );
else
{
    $query = ( $query === false || !is_numeric($query) ) ? null : abs( (int)$query );
    $ace = RPCAce::get_blocklist( $query, BLOCKS_PER_LIST );
    $query = $query === null ? @$ace['num_blocks'] : $query;
}
if( isset($ace['err']) || RETURN_JSON === true )
    die( 'RPC Ace error: ' . (RETURN_JSON ? $ace : $ace['err']) );

echo get_n_coins($ace['num_blocks'])

?>
