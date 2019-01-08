<?php
require './vendor/autoload.php';
use Workerman\Worker;
use GuzzleHttp\Client;
use QL\QueryList;
use Workerman\Connection\AsyncTcpConnection;

class App
{
    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
        $this->project = explode('/', $url)[4];
    }


    public function start()
    {
        $worker = new Worker("text://0.0.0.0:2345");

        $worker->onWorkerStart = function () {

            $rules = array(
                'version' =>
                    array(
                    0 => '.release-entry .release-header .css-truncate-target',
                    1 => 'text',
                ),
                'assets' =>
                    array(
                    0 => '.release-entry details',
                    1 => 'html',
                ),
            );

            $rule = array(
                'link' =>
                    array(
                    0 => 'li a',
                    1 => 'href',
                )
            );

            $client = new GuzzleHttp\Client();
            $afterVersion = '';
            $stop = false;
            do {
                echo "开始拉取\n";
                $url = "{$this->url}/releases" . $afterVersion;
                echo $url . "\n";

                $res = $client->request('GET', $url);
                $data = QueryList::html($res->getBody())->find('.pagination a')->htmls()->all();
                $htmls = QueryList::html($res->getBody())->rules($rules)->range('')->queryData();

                foreach ($htmls as $key => $item) {
                    if (isset($item['version']) && isset($item['assets']) && !empty($item['assets'])) {
                        $list = QueryList::html($item['assets'])->rules($rule)->queryData();
                        $links = array_column($list, 'link');
                        $item['links'] = $links;
                        $task = new AsyncTcpConnection('text://127.0.0.1:2346');
                        $task->onConnect = function($connection) use($item)
                        {
                            $connection->send(json_encode(['project' => $this->project, 'data' => $item]));
                        };
                        $task->connect();
                    } else {
                        unset($htmls[$key]);
                    }
                }
                $version = end($htmls)['version'];
                $afterVersion = "?after={$version}";
                echo "完成\n";
                if (count($data) == 1 && $data[0] == 'Previous') {
                    echo "工作完成了\n";
                    $stop = true;
                }
                sleep(1);
            } while ($stop === false);


        };
        Worker::runAll();
    }


}

$app = new App('https://github.com/vuejs/vue');
$app->start();