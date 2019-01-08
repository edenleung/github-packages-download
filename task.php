<?php
require './vendor/autoload.php';
use Workerman\Worker;
use GuzzleHttp\Client;
use QL\QueryList;

class Task
{
    protected $http;
    public function __construct()
    {
        $this->http = new GuzzleHttp\Client();
    }

    public function start()
    {
        $worker = new Worker("text://0.0.0.0:2346");
        $worker->onWorkerStart = function () {};
        $worker->onMessage = function ($connection, $data)
        {
            $res = json_decode($data);
            $project = $res->project;
            $data = $res->data;
            $dir = "./packages/{$project}";
            if (!file_exists($dir)){
                mkdir ($dir, 0777, true);
            }

            foreach($data->links as $path) {
                $filename = explode('/', $path);
                $res = $this->http->request('GET', 'https://github.com' . $path);
                file_put_contents($dir .'/'. $filename[4], $res->getBody());
                echo "下载{$filename[4]}完成";
            }
        };
        Worker::runAll();
    }


}

$app = new Task();
$app->start();