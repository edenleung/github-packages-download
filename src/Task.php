<?php
use Workerman\Worker;
use GuzzleHttp\Client;
use QL\QueryList;

require './vendor/autoload.php';

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
        $worker->count = 200;
        $worker->onWorkerStart = function () {
        };
        $worker->onMessage = function ($connection, $data) {
            $res = json_decode($data);
            $project = $res->project;
            $data = $res->data;
            $version = $data->version;
            $dir = "./packages/{$project}/{$version}";
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $loop = \React\EventLoop\Factory::create();
            $filesystem = \React\Filesystem\Filesystem::create($loop);

            foreach ($data->links as $path) {
                $filename = explode('/', $path);
                $file = end($filename);
                $savePath = $dir . '/' . $file;
                if (!file_exists($savePath)) {
                    echo "正在下载: {$path}\n";
                    $request = new \GuzzleHttp\Psr7\Request('GET', 'https://github.com' . $path);

                    $promise = $this->http->sendAsync($request)->then(function ($response) use($filesystem, $savePath, $file) {
                        $filesystem->file($savePath)->putContents($response->getBody());
                        echo "下载{$file}完成\n";
                    });
                    $promise->wait();
                    
                } else {
                    echo "{$path} 文件已下载，终止下载\n";
                }
            }

            $loop->run();
        };
        Worker::runAll();
    }
}
