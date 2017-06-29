<?php

/*
NaiveChain Implemented in PHP
by Abdurrahman Shofy Adianto [ https://azophy.github.io ]
adapted from Lauri Hartikk's code [ https://github.com/lhartikk/naivechain ]
*/

class Server {
	public 	$peers = [];

	public function __construct($selfUrl) {
		$this->selfUrl = $selfUrl;
		$this->peers = [ $this->selfUrl ];
	}

	public function broadcast($data) {
		foreach ($this->peers as $peer) if ($peer != $this->selfUrl)
			{ self::postToUrl($peer, $data); }
	}

	static function runServer($url, $response) {
		$socket = stream_socket_server("tcp://".$url, $errno, $errstr);
		if (!$socket) {
		  echo "$errstr ($errno)<br />\n";
		} else {
		  echo 'server is running on '.$url;
		  while (true) {
			$conn = stream_socket_accept($socket);
			$response($conn);
		    fclose($conn);
		  }
		  fclose($socket);
		}
	}

	static function postToUrl($url, $data) {
		$client = stream_socket_client("tcp://$url", $errno, $errorMessage);

		if ($client === false)
		    throw new UnexpectedValueException("Failed to connect: $errorMessage");

		fwrite($client, json_encode($data));
		$res = stream_get_contents($client);
		fclose($client);
		return $res;

	}
}

class Block {
	public function __construct($index, $previousHash, $timestamp, $data, $hash) {
		$this->index 		= $index;
		$this->previousHash = $previousHash;
		$this->timestamp 	= $timestamp;
		$this->data 		= $data;
		$this->hash 		= $hash;
	}

	static function calculateHash($index, $previousHash, $timestamp, $data) {
		return hash("sha256", $index.$previousHash.$timestamp.$data);
	}

	public function calculateThisHash() {
		return self::calculateHash( 
			$this->index,
			$this->previousHash,
			$this->timestamp,
			$this->data
		);
	}

	static function parse($arr) {
		return new Block(
			$arr['index'], 
			$arr['previousHash'], 
			$arr['timestamp'], 
			$arr['data'], 
			$arr['hash']
		);
	}
}

class BlockChain {
	public $chain = [];

	public function __construct($selfUrl) {
		$this->selfUrl = $selfUrl;
		$this->network = new Server($this->selfUrl);
		$this->network->broadcast(['query' => 'sendMeAllBlocks', 'url' => $this->selfUrl ]);
		if (count($this->network->peers) <= 1)
			$this->chain = [ self::getGenesisBlock() ];
	}

	static function getGenesisBlock() {
	    return new Block(0, "0", 1465154705, "my genesis block!!", "816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7");
	}

	public function getLatestBlock() {
		return $this->chain[count($this->chain)-1];
	}


	public function addBlock($block) {
		if ($this->isValidNewBlock($block, $this->getLatestBlock())) {
			$this->chain[] = $block;
		}
	}
	
	public function generateNextBlock($blockData) {
	    $previousBlock = $this->getLatestBlock();
	    $nextIndex = $previousBlock->index + 1;
	    $nextTimestamp = time() / 1000;
	    $nextHash = Block::calculateHash($nextIndex, $previousBlock->hash, $nextTimestamp, $blockData);
	    
	    return new Block($nextIndex, $previousBlock->hash, $nextTimestamp, $blockData, $nextHash);
	}

	static function isValidNewBlock ($newBlock, $previousBlock) {
	    if ($previousBlock->index + 1 !== $newBlock->index) {
	        echo 'invalid index';
	        return false;
	    } else if ($previousBlock->hash !== $newBlock->previousHash) {
	        echo 'invalid previoushash';
	        return false;
	    } else if ($newBlock->calculateThisHash() !== $newBlock->hash) {
	        echo ('invalid hash: ' . $newBlock->calculateThisHash() . ' ' . $newBlock->hash);
	        return false;
	    }
	    return true;
	}

	static function isValidChain($blockchainToValidate) {
	    if (json_encode($blockchainToValidate[0]) !== json_encode(self::getGenesisBlock())) {
	        return false;
	    }
	    $tempBlocks = [ $blockchainToValidate[0]] ;
	    for ($i = 1; $i < count($blockchainToValidate); $i++) {
	        if (self::isValidNewBlock($blockchainToValidate[$i], $tempBlocks[$i - 1])) {
	            $tempBlocks[] = $blockchainToValidate[$i];
	        } else {
	            return false;
	        }
	    }
	    return true;
	}

	public function replaceChain($newBlocks) {
	    if (self::isValidChain($newBlocks) && count($newBlocks) > count($blockchain)) {
	        echo ('Received blockchain is valid. Replacing current blockchain with received blockchain');
	        $blockchain = $newBlocks;
	        $this->network->broadcast(['query' => 'addBlock', 'data' => [ $this->network->getLatestBlock() ] ]);
	    } else {
	        echo ('Received blockchain invalid');
	    }
	}
}

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
	$server_url = (isset($argv[1])) ? $argv[1] : '127.0.0.1:8000';

	$bc = new BlockChain($server_url);

	Server::runServer($server_url, function($conn) use ($bc) {
		$req = json_decode(fread($conn, 1024), true); print_r($req);
		$res = null;
		switch ($req['query']) {
			case 'blocks': $res = json_encode($bc->chain); break;

			case 'sendMeAllBlocks':
				Server::postToUrl($req['url'], ['query' => 'addBlock', 'data' => $bc->chain ]);
				break;
			
			case 'mineBlock':
				$newBlock = $bc->generateNextBlock($req['data']);
		        $bc->addBlock($newBlock);
		        $bc->network->broadcast(['query' => 'addBlock', 'data' => [ $newBlock ]]);
		        $res = ('block added: ' . json_encode($newBlock));
				break;

			case 'addBlock':
				$receivedBlocks = $req['data'];
				uasort($receivedBlocks, function($a,$b) { return $a['index']-$b['index'];});
			    $latestBlockReceived = Block::parse($receivedBlocks[count($receivedBlocks) - 1]);
			    $latestBlockHeld = $bc->getLatestBlock();
			    if ($latestBlockReceived->index > $latestBlockHeld->index) {
			        error_log('blockchain possibly behind. We got: ' . $latestBlockHeld->index . ' Peer got: ' . $latestBlockReceived->index);
			        if ($latestBlockHeld->hash === $latestBlockReceived->previousHash) {
			            error_log("We can append the received block to our chain");
			            $bc->addBlock($latestBlockReceived);
			            $bc->network->broadcast(['query' => 'addBlock', 'data' => [ $bc->getLatestBlock() ]]);
			        } else if (count($receivedBlocks) === 1) {
			            error_log("We have to query the chain from our peer");
			            $bc->network->broadcast(['query' => 'sendMeAllBlocks', 'url' => $bc->selfUrl ]);
			        } else {
			            error_log("Received blockchain is longer than current blockchain");
			            $bc->replaceChain($receivedBlocks);
			        }
			    } else {
			        error_log('received blockchain is not longer than received blockchain. Do nothing');
			    }

			case 'allPeers':
				$res = json_encode($bc->network->peers);
				break;

			case 'addPeer':
				if (!in_array($req['url'], $bc->network->peers)) $bc->network->peers[] = $req['url'];
				$res = "success\n";
				break;

		}

		fwrite($conn, $res);
	});
}