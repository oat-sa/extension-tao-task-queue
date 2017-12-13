<?php

namespace oat\taoTaskQueue\model\Task;


interface RemoteTaskSynchroniserInterface
{
    public function getRemoteStatus();
}