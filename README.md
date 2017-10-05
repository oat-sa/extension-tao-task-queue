# Task Queue

> This article describes the functioning of the new Task Queue.

## Install

You can add the Task Queue as a standard TAO extension to your current TAO instance.

```bash
 $ composer require oat-sa/extension-tao-task-queue
```

## Components

The task queue system is built on three main components.

### Queue component

It is responsible for handling different types of queue brokers and mainly for publishing and receiving of tasks.
This is the _**main service**_ to be used for interacting with the queue system.

The communication with the different queues is done through the Message Brokers. There are three type of message brokers currently:
- **InMemoryQueueBroker** which accomplishes the Sync Queue mechanism. Tasks will be executing straightaway after adding them into the queue.
- **RdsQueueBroker** which stores tasks in RDS.
- **SqsQueueBroker** which is for using AWS SQS.

### Worker component

Its duty is to get a **Task** from the specified queue and execute it. **Multiple workers can be run at the same time.**
It has built-in signal handling for the following actions:
 - Shutting down the worker gracefully: SIGTERM/SIGINT/SIGQUIT
 - Pausing task processing: SIGUSR2
 - Resuming task processing: SIGCONT
 
After processing the given task, the worker saves the generated report for the task through the Task Log.

#### Running a worker

To run a worker, use the following command. It will start a worker for running infinitely.

```bash
 $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\RunWorker'
```

If you want the worker running for a specified time/iteration, use this one:

```bash
 $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\RunWorker' 5
```

#### Initializing the queue and the task log container

You can run this script if you want to be sure that the required queue and the task log container are created.
If they are already exist, no action will be taken.

```bash
 $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue'
```

### Task Log component
It is responsible for managing the lifecycle of Tasks, can be accessed as a service. It stores the statuses, 
the generated report and some other useful metadata. 
Its main duty is preventing of running the same task by multiple workers at the same time. 

It can also have multiple brokers extending TaskLogBrokerInterface to store the data in different type of storage system. 
Currently we have **RdsTaskLogBroker** which uses RDS.

Usually, you won't have to interact with this service directly except if you are using InMemoryQueueBroker and want to get the report of the given task in the same request.

## Usage examples

- Getting the queue service as usual:

```php
$queue = $this->getServiceManager()->get(\oat\taoTaskQueue\model\QueueInterface::SERVICE_ID);
```

### Working with Task

There is two ways to create and publish a task.

- **First option**: creating a task class extending \oat\taoTaskQueue\model\AbstractTask. It's a new way, use it if you like it and if you don't need the possibility to run your task as an Action from CLI.
```php
<?php

use \common_report_Report as Report;
use \oat\taoTaskQueue\model\AbstractTask;

class MyFirstTask extends AbstractTask
{
    // constants for the param keys
    const PARAM_TEST_URI = 'test_uri';
    const PARAM_DELIVERY_URI = 'delivery_uri';

    /**
     * As usual, the magic happens here.
     * It needs to return a Report object. 
     */
    public function __invoke()
    {
        // you get the parameter using getParameter() with the required key
        if (!$this->getParameter(self::PARAM_TEST_URI) || !$this->getDeliveryUri()) {
            return Report::createFailure('Missing parameters');
        }

        $report = Report::createSuccess();
        $report->setMessage("I worked with Test ". $this->getParameter(self::PARAM_TEST_URI) ." and Delivery ". $this->getDeliveryUri());

        return $report;
    }

    /**
     * You can create a custom setter for your parameter.
     *
     * @param $uri
     */
    public function setDeliveryUri($uri)
    {
        // doing some validation
        // if it's a valid delivery
        $this->setParameter(self::PARAM_DELIVERY_URI, $uri);
    }

    /**
     * You can create a custom getter for your parameter.
     *
     * @return mixed
     */
    public function getDeliveryUri()
    {
        return $this->getParameter(self::PARAM_DELIVERY_URI);
    }
}
```

Then you can initiate your class and setting the required parameters and finally publish it:
```php
$myTask = new MyFirstTask();
$myTask->setParameter(MyFirstTask::PARAM_TEST_URI, 'http://taotesting.com/tao.rdf#i1496838551505670');
$myTask->setDeliveryUri('http://taotesting.com/tao.rdf#i1496838551505110');

if ($queue->enqueue($myTask)) {
    echo "Successfully published";
}
```

- **Second option**: Using Command/Action objects which implement \oat\oatbox\action\Action. This is the usual old way and more preferable because we can run those actions from CLI if needed.

```php
$task = $queue->createTask(new RegeneratePayload(), array($delivery->getUri()));
if ($task->isEnqueued()) {
    echo "Successfully published";
}
```

As you can see, nothing has changed here. It is the same like before. The magic is behind of the createTask() method. Look into it if you dare...

Anyway, the main thing here is that a wrapper class called \oat\taoTaskQueue\model\CallbackTask is used to wrap your Action object and make it consumable for the queue system.

#### Working with Task Log component

Mostly, it can be used when the queue is used as Sync Queue and you want to get the status and the report for a task:

```php
/** @var \oat\taoTaskQueue\model\TaskLogInterface $taskLog */
$taskLog = $this->getServiceManager()->get(\oat\taoTaskQueue\model\TaskLogInterface::SERVICE_ID);

// checking the status for STATUS_COMPLETED can prevent working with a null report if InMemoryQueueBroker not used anymore.
if ($task->isEnqueued() && $taskLog->getStatus($task->getId()) == TaskLogInterface::STATUS_COMPLETED) {
    $report = $taskLog->getReport($task->getId());
}
```

