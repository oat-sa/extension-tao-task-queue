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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\scripts\tools;

use oat\oatbox\extension\InstallAction;
use Aws\Exception\AwsException;
use oat\taoTaskQueue\model\QueueBroker\InMemoryQueueBroker;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\QueueBroker\SqsQueueBroker;
use oat\taoTaskQueue\model\QueueDispatcher;
use oat\taoTaskQueue\model\QueueDispatcherInterface;
use oat\taoTaskQueue\model\TaskLogInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * - Without any parameter, it uses the current settings for initialization:
 *   - creates the queues
 *   - creates the task log container
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue'
 * ```
 *
 * - Using Sync Queues. Every existing queue will be changed to use InMemoryQueueBroker.
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --broker=memory
 * ```
 *
 * - Using RDS Queues. Every existing queue will be changed to use RdsQueueBroker. You can set the following parameters:
 *  - persistence: Required
 *  - receive: Optional (Maximum amount of tasks that can be received when polling the queue)
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --broker=rds --persistence=default --receive=10
 * ```
 *
 * - Using SQS Queues. Every existing queue will be changed to use SqsQueueBroker. You can set the following parameters:
 *  - aws-profile: Required
 *  - receive: Optional (Maximum amount of tasks that can be received when polling the queue)
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue' --broker=sqs --aws-profile=default --receive=10
 * ```
 */
class InitializeQueue extends InstallAction
{
    use ServiceLocatorAwareTrait;

    const BROKER_MEMORY = 'memory';
    const BROKER_RDS = 'rds';
    const BROKER_SQS = 'sqs';

    private $wantedBroker;
    private $persistenceId;
    private $awsProfile;
    private $receive;

    public function __invoke($params)
    {
        try {
            $this->checkParams($params);

            /** @var QueueDispatcher $queueService */
            $queueService = $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);

            $reRegister = false;

            // if any new change is wanted on queues
            if (count($params) > 0) {
                $broker = null;

                switch ($this->wantedBroker) {
                    case self::BROKER_MEMORY:
                        $broker = new InMemoryQueueBroker();
                        break;

                    case self::BROKER_RDS:
                        $broker = new RdsQueueBroker($this->persistenceId, $this->receive ?: 1);
                        break;

                    case self::BROKER_SQS:
                        $broker = new SqsQueueBroker(/*$this->awsProfile,*/\common_cache_Cache::SERVICE_ID, $this->receive ?: 1);
                        break;
                }

                foreach ($queueService->getQueues() as $queue) {
                    $queue->setBroker(clone $broker);
                }

                $reRegister = true;
            }

            // Create queues
            if (!$queueService->isSync()) {
                $queueService->initialize();
            }

            if ($reRegister) {
                $this->registerService(QueueDispatcherInterface::SERVICE_ID, $queueService);
            }

            // Create task log container
            /** @var TaskLogInterface $taskLog */
            $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);
            $taskLog->createContainer();

            return \common_report_Report::createSuccess('Initialization successful');
        } catch (AwsException $e) {
            return \common_report_Report::createFailure($e->getAwsErrorMessage());
        } catch (\Exception $e) {
            return \common_report_Report::createFailure($e->getMessage());
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
                    if (!in_array($value, [self::BROKER_MEMORY, self::BROKER_RDS, self::BROKER_SQS])) {
                        throw new \InvalidArgumentException('Broker "'. $value .'" is not a valid broker option. Valid options: '. implode(', ', [self::BROKER_MEMORY, self::BROKER_RDS, self::BROKER_SQS]));
                    }

                    $this->wantedBroker = $value;
                    break;

                case '--persistence':
                    $this->persistenceId = $value;
                    break;

                /*case '--aws-profile':
                    $this->awsProfile = $value;
                    break;*/

                case '--receive':
                    $this->receive = abs((int) $value);
                    break;
            }
        }

        if ($this->wantedBroker == self::BROKER_RDS && !$this->persistenceId) {
            throw new \InvalidArgumentException('Persistence id (--persistence=...) needs to be set for RDS.');
        }

        /*if ($this->wantedBroker == self::BROKER_SQS && !$this->awsProfile) {
            throw new \InvalidArgumentException('AWS profile (--aws-profile=...) needs to be set for SQS.');
        }*/
    }
}

