<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2017 - 2020 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\scripts\tools;

use common_report_Report as Report;
use InvalidArgumentException;
use oat\oatbox\extension\InstallAction;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\taskQueue\Queue\TaskSelector\SelectorStrategyInterface;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * - Without any parameter, it uses the current settings for initialization:
 *   - creates the queues
 *   - creates the task log container
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue'
 * ```
 *
 * - Using Sync Queues. Every existing queue will be changed to use InMemoryQueueBroker if there is no queue specified.
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --broker=memory [--queue=myQueue]
 * ```
 *
 * - Using RDS Queues. Every existing queue will be changed to use RdsQueueBroker if there is no queue specified. You can set the following parameters:
 *  - persistence: Required
 *  - receive: Optional (Maximum amount of tasks that can be received when polling the queue)
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --broker=rds --persistence=default --receive=10 [--queue=myQueue]
 * ```
 *
 * - Using SQS Queues. Every existing queue will be changed to use SqsQueueBroker if there is no queue specified. You can set the following parameters:
 *  - receive: Optional (Maximum amount of tasks that can be received when polling the queue)
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --broker=sqs --receive=10 [--queue=myQueue]
 * ```
 *
 * - To set a task selector strategy, please provide the FQCN of the wanted strategy
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --strategy="\oat\taoTaskQueue\model\TaskSelector\StrictPriorityStrategy"
 */
class InitializeQueue extends InstallAction
{
    use ServiceLocatorAwareTrait;

    private const AVAILABLE_BROKERS = [
        BrokerFactory::BROKER_MEMORY,
        BrokerFactory::BROKER_RDS,
        BrokerFactory::BROKER_NEW_SQL,
        BrokerFactory::BROKER_SQS,
    ];

    /** @var string */
    private $wantedBroker;
    /** @var string */
    private $persistenceId;
    /** @var int */
    private $receive;
    /** @var string */
    private $queue;
    /** @var SelectorStrategyInterface */
    private $strategy;

    public function __invoke($params)
    {
        try {
            $this->checkParams($params);

            $report = Report::createInfo('Running command...');

            /** @var QueueDispatcherInterface|ConfigurableService $queueService */
            $queueService = $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);

            $registerBroker = $this->registerBroker($params, $queueService);

            // Create queues
            if (!$queueService->isSync()) {
                $queueService->initialize();
                $report->add(Report::createSuccess('Queue(s) initialized.'));
            }

            if ($registerBroker) {
                $this->registerService(QueueDispatcherInterface::SERVICE_ID, $queueService);
                $report->add(Report::createSuccess('Queue service re-registered.'));
            }

            // Create task log container
            /** @var TaskLogInterface $taskLog */
            $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);
            $taskLog->createContainer();
            $report->add(Report::createSuccess('Task Log container created.'));

            return $report;
        } catch (\Exception $e) {
            return Report::createFailure($e->getMessage());
        }
    }

    /**
     * @param array $params
     */
    private function checkParams(array $params)
    {
        foreach ($params as $param) {
            list($option, $value) = explode('=', $param);

            switch ($option) {
                case '--broker':
                    if (!in_array($value, self::AVAILABLE_BROKERS)) {
                        throw new InvalidArgumentException(
                            sprintf('Broker "%s" is not a valid broker option. Valid options: %s',
                                $value,
                                implode(', ', self::AVAILABLE_BROKERS)
                            )
                        );
                    }

                    $this->wantedBroker = $value;
                    break;

                case '--persistence':
                    $this->persistenceId = $value;
                    break;

                case '--receive':
                    $this->receive = abs((int) $value);
                    break;

                case '--queue':
                    $this->queue = (string) $value;
                    break;

                case '--strategy':
                    if (!class_exists($value)) {
                        throw new InvalidArgumentException('Strategy "' . $value . '" does not exist.');
                    }

                    $this->strategy = new $value();
                    break;
            }
        }
    }

    private function registerBroker(array $params, QueueDispatcherInterface $queueService): bool
    {
        $reRegister = false;
        // if any new change is wanted on queues
        if (count($params) > 0) {
            // BROKER settings
            if ($this->wantedBroker) {
                $brokerFactory = $this->getBrokerFactory();
                $broker = $brokerFactory->create($this->wantedBroker, $this->persistenceId, $this->receive ?: 1);

                if (!is_null($this->queue)) {
                    $queue = $queueService->getQueue($this->queue);
                    $queue->setBroker(clone $broker);
                } else {
                    foreach ($queueService->getQueues() as $queue) {
                        $queue->setBroker(clone $broker);
                    }
                }
            }

            // STRATEGY settings
            if ($this->strategy) {
                $queueService->setTaskSelector($this->strategy);
            }

            $reRegister = true;
        }

        return $reRegister;
    }

    private function getBrokerFactory(): BrokerFactory
    {
        return $this->getServiceLocator()->get(BrokerFactory::class);
    }
}
