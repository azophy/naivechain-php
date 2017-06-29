BlockChain in 200-ish line of PHP codes
=======================================

For the past 2 days I have been attempting to convert a magnificent piece of code created by Lauri Hartikka. The simple script, dubbed as "NaiveChain" actually provide with many insight in understanding this interesting piece of technology. For a couple of months, I've been struggling to wrap my head on this crazy concept. Blockchain to this days remains as some sort of a mythical creatures that lots of people believe in, but rarely ever been sighted on site, at lease source code-wise.

In that account, as I'm more of a person who leran a whole lot from creating (and breaking) thins by myself, I try to learn from existing code about this topic. Aound that time that I stumbled upon Hartikka's code, and decided to implement the code myself, but in the language I'm most familiar with : PHP.

To be honest, this attempt turned out to be a whole lot more challanging than what I originally expected. But the pays sure is worth it. In the process I realize that at least 

# Usage:
## server
from the directory just run ```"php naivechain.php <IP-address>:<post>"```. example: 

	php naivechain.php 127.0.0.1:8000

## client
to access sever manually you could use telnet:
	
	$ telnet 127.0.0.1 8000
	> {"query":"blocks"}

or if you dont like to create your own json message, you could put up a php console on this project's folder, then include naivechain.php

    $ php -a
    Interactive mode enabled
    php > include('naivechain.php');
    php > print_r(Server::postToUrl(
    php (     '127.0.0.1:8000',[
    php (     'query' => 'blocks',
    php ( ]));
    [{"index":0,"previousHash":"0","timestamp":1465154705,"data":"my genesis block!!","hash":"816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7"}]
    php > 

# Example scenario:
First, start up 3 different terminal session. We will use the first & second session as our node, and the thirs to communicate with the two.

In the first & second session, load up server using different port, so it will be each's respective address

    in the first session: php naivechain.php 127.0.0.1:8000
    in the second session: php naivechain.php 127.0.0.1:8080

Then add each other as a peer
    
    $ php -a
    Interactive mode enabled
    php > include('naivechain.php');
    php > print_r(Server::postToUrl(
    php (     '127.0.0.1:8000',[
    php (     'query' => 'addPeer',
    php (     'url'   => '127.0.0.1:8080'
    php ( ]));
    success
    php > print_r(Server::postToUrl(
    php (     '127.0.0.1:8080',[
    php (     'query' => 'addPeer',
    php (     'url'   => '127.0.0.1:8000'
    php ( ]));
    success
    php > 

Next we could try to input new entry to the blockchain

    php > print_r(Server::postToUrl(
    php (     '127.0.0.1:8000',[
    php (     'query' => 'mineBlock',
    php (     'data'  => 'testing data input'
    php ( ]));
    php > 

To verify, just output each's blockchain

    php > print_r(Server::postToUrl(
    php (     '127.0.0.1:8000',[
    php (     'query' => 'blocks',
    php ( ]));
    [{"index":0,"previousHash":"0","timestamp":1465154705,"data":"my genesis block!!","hash":"816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7"},{"index":1,"previousHash":"816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7","timestamp":1498725.646,"data":"testing data input","hash":"b44f0785641d7601d0fca59d8c096e950795e0d4ec8cc1895db6002cffbd9b56"}]
    php > print_r(Server::postToUrl(
    php (     '127.0.0.1:8080',[
    php (     'query' => 'blocks',
    php ( ]));
    [{"index":0,"previousHash":"0","timestamp":1465154705,"data":"my genesis block!!","hash":"816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7"},{"index":1,"previousHash":"816534932c2b7154836da6afc367695e6337db8a921823784c14378abed4f7d7","timestamp":1498725.646,"data":"testing data input","hash":"b44f0785641d7601d0fca59d8c096e950795e0d4ec8cc1895db6002cffbd9b56"}]
    php > 

# Explanation
Basically this script is comprised of 3 section:

## the Server Class
This class store out p2p network configuration, as well provide basic methods for creating server & connecing to a client. In this code I choose to use PHP's Socket Stream functionalities as it is the easiest and error-proof in my experience. You could use other means to implemented it, such as using standard HTTP servicing. But for myself, I think using socket is the more straightforward for this use.

If you are interested in PHP Socket Stream (its different from "socket_" methods which is more easily found in the internet about PHP's Socket Programming, I recommended the 2 articles I put up belows. It really is worth reading).

## the Block & BlockChain class
As you can guess, both class are where the actual BlockChain definition is. Its quite straightforward, so I guess you wouldn't have many troubles reading it yourself. And I suggest to read Hartikk's original article first if you haven't

## the "controller" section
Despite the actual BlockChain definition is quite easy to understanding, many portion of what making BlockChain difficult to understand is actualy in the protocol implementation. The last section is where the Socket Server is actually initiated, and also the place where the incoming request is processed. Unlike Hartikk's aproach which use separate server to handle P2P and blockhain queries, here I decided to combine both into one request handler.

Currently I only implemented 6 queries:

- allPeers
- addPeer
- blocks
- addBlock
- sendMeAllBlocks : this is the request that is being broadcasted to get the latest block
- mineBlock : the actual part where the data is stored. Note that this implementation use no 'proof-of-work' or such, thus every request would directly being wrapped into a blocked and broadcasted into the network.

# Conclusion
As stated in Hartikk's post, this "NaiveChain" implementation is only aimed to show you what is blockchain and how it works, without mixing the explanation with what its tryining to solve (Proof-of-work, Smart Contract, Transactions, etc). I myself still in the process of learning this amazing piece of technology, so I open up for discussion. If you have any questions or may be some corrections, just mail me by azophy at gmail dot com, or chat me in telegram @azophy. Thanks for reading.

# References
* The original code:
    - article : https://medium.com/@lhartikk/a-blockchain-in-200-lines-of-code-963cc1cc0e54
    - source code : https://github.com/lhartikk/naivechain
* on PHP Socket Stream for connection:
    - https://shane.logsdon.io/posts/simple-php-socket-programming/
    - https://www.christophh.net/2012/07/24/php-socket-programming/