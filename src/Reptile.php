<?php
use Workerman\Worker;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Workerman\Connection\AsyncTcpConnection;
require './vendor/autoload.php';

class Reptile
{
    protected $url;

    protected $httpClient;

    protected $afterVersion = '';

    public function __construct($url)
    {
        $this->url = $url;
        $this->project = explode('/', $url)[4];
    }

    public function start()
    {
        $worker = new Worker("text://0.0.0.0:2345");

        $worker->onWorkerStart = function () {
            $this->httpClient = new GuzzleHttp\Client();
            $this->fetch();
        };

        Worker::runAll();
    }

    protected function fetch()
    {
        echo "开始拉取\n";
        $url = "{$this->url}/releases" . $this->afterVersion;
        echo $url . "\n";

        $res = $this->httpClient->request('GET', $url);
        $html = $res->getBody()->getContents();
        $crawler = new Crawler($html);

        if (!count($crawler->filter('.release-entry'))) {
            echo "啥包都没有，结束了\n";
        } else {
            $crawler->filter('.release-entry')->each(function (Crawler $node, $i) {
                if (count($node->filter('.css-truncate-target'))) {
                    $temp = [];
                    $temp['version'] = $node->filter('.css-truncate-target')->text();
                    $node->filter('.release-entry details a')->each(function(Crawler $assetsNode, $ii) use(&$temp){
                        $temp['links'][] = $assetsNode->attr('href');
                    });

                    $task = new AsyncTcpConnection('text://127.0.0.1:2346');
                    $task->onConnect = function($connection) use($temp)
                    {
                        $connection->send(json_encode(['project' => $this->project, 'data' => $temp]));
                    };
                    
                    $task->connect();
                }
                
            });

            $lasterVersion = $crawler->filter('.release-entry .release-header .css-truncate-target')->last()->text();
            $this->afterVersion = "?after={$lasterVersion}";

            // 分析 分页数据
            $pagination = $crawler->filter('.pagination a');
            $firstPage = $pagination->first();
            if (count($pagination) == 1 && $firstPage->text() == 'Previous') {
                echo "工作完成了\n";
            } else {
                $this->fetch();
            }
        }
    }

}
