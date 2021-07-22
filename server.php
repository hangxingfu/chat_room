<?php

class Server
{
    private $serv;

    public function __construct()
    {
        $this->serv = new swoole_websocket_server("0.0.0.0", 9502);
        $this->serv->set([
            'worker_num' => 2,
            'max_request' => 4,
            'task_worker_num' => 4,
            'dispatch_mode' => 4,
            'daemonize' => false,
        ]);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Open', [$this, 'onOpen']);
        $this->serv->on("Message", [$this, 'onMessage']);
        $this->serv->on("Close", [$this, 'onClose']);
        $this->serv->on("Task", [$this, 'onTask']);
        $this->serv->on("Finish", [$this, 'onFinish']);

        $this->serv->start();
    }

    /**
     * 打开server
     * @param $serv
     */
    public function onStart($serv)
    {
        echo "#### onStart ####" . PHP_EOL;
        echo "SWOOLE " . SWOOLE_VERSION . " 服务已启动" . PHP_EOL;
        echo "master_pid: {$serv->master_pid}" . PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}" . PHP_EOL . PHP_EOL;
    }

    /**
     * 与前端进行连接
     * @param $serv
     * @param $request
     */
    public function onOpen($serv, $request)
    {
        echo "#### onOpen ####" . PHP_EOL;
        echo $request->header['host'] . PHP_EOL;
        echo $request->header['accept-language'] . PHP_EOL;
        echo $request->get['username'] . PHP_EOL;
        $username = $request->get['username'];
        echo "server: handshake success with fd{$request->fd}" . PHP_EOL;
        $serv->task([
            'type' => 'login',
            'fd' => $request->fd,
            'name' => $username
        ]);
        echo "########" . PHP_EOL . PHP_EOL;
    }

    /**
     * 执行任务代码
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
        echo "#### onTask ####" . PHP_EOL;
        echo "#{$serv->worker_id} onTask: [PID={$serv->worker_pid}]: task_id={$task_id}" . PHP_EOL;
        $msg = '';
        switch ($data['type']) {
            case 'login':
                $msg = '我来了...';
                break;
            case 'speak':
                $msg = $data['msg'];
                break;
        }
        $respones_data = json_encode(['name' => $data['name'], 'msg' => $msg]);
        foreach ($serv->connections as $fd) {
            if ($fd != $data['fd']) {
                $connectionInfo = $serv->connection_info($fd);
                if ($connectionInfo['websocket_status'] == 3) {
                    $serv->push($fd, $respones_data); //长度最大不得超过2M
                }
            }
        }
        $serv->finish($data);
        echo "########" . PHP_EOL . PHP_EOL;
    }

    /**
     * 接受客户端发来的消息
     * @param $serv
     * @param $frame
     */
    public function onMessage($serv, $frame)
    {
        echo "#### onMessage ####" . PHP_EOL;
        echo "receive from fd{$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}" . PHP_EOL;
        $send_data = json_decode($frame->data,true);
        echo "#### sendData ####" . PHP_EOL;
        echo $send_data['msg'] . PHP_EOL;
        echo "#### sendData ####" . PHP_EOL;
        $serv->task([
            'type' => 'speak',
            'msg' => $send_data['msg'] ?? '默认的msg',
            'name' => $send_data['name'] ?? '默认的name',
            'fd' => $frame->fd,
        ]);
        echo "########" . PHP_EOL . PHP_EOL;
    }

    /**
     * 完成任务的回调
     * @param $serv
     * @param $task_id
     * @param $data
     */
    public function onFinish($serv, $task_id, $data)
    {
        echo "#### onFinish ####" . PHP_EOL;
        echo "Task {$task_id} 已完成" . PHP_EOL;
        echo "########" . PHP_EOL . PHP_EOL;
    }

    /**
     * 客户端断开连接回调
     * @param $serv
     * @param $fd
     */
    public function onClose($serv, $fd)
    {
        echo "#### onClose ####" . PHP_EOL;
        echo "client {$fd} closed" . PHP_EOL;
        echo "########" . PHP_EOL . PHP_EOL;
    }

}

new Server();