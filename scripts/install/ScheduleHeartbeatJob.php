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
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA
 *
 */
namespace oat\taoTaskQueue\scripts\install;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\InstallAction;
use oat\tao\helpers\UserHelper;
use oat\taoTaskQueue\scripts\tools\QueueHeartbeat;
use oat\taoScheduler\model\scheduler\SchedulerServiceInterface;
use oat\tao\model\accessControl\AclProxy;
use oat\oatbox\user\User;

/**
 * Class ScheduleHeartbeatJob
 *
 * Schedule a job to put heartbeat tasks to the each configured task queue.
 * Run example:
 * ```
 * sudo -u www-data php index.php '\oat\taoTaskQueue\scripts\install\ScheduleHeartbeatJob' 'http://sample/first.rdf#i1534774676184875' '* * * * *'
 * ```
 *
 * @package oat\taoTaskQueue
 * @author Aleh Hutnikau, <hutnikau@1pt.com>
 */
class ScheduleHeartbeatJob extends InstallAction
{
    use OntologyAwareTrait;

    public function __invoke($params)
    {
        $user = $this->getUser($params);
        $rRule = $this->getRRule($params);
        if (isset($params[1])) {
            $rRule = $params[1];
        }
        $schedulerService = $this->getServiceManager()->get(SchedulerServiceInterface::SERVICE_ID);
        $schedulerService->attach($rRule, new \DateTime('now', new \DateTimeZone('utc')), QueueHeartbeat::class, [$user->getIdentifier()]);
    }

    /**
     * @param array $params
     * @return mixed|string
     */
    public function getRRule(array $params)
    {
        $rRule = '*/30 * * * *';
        if (isset($params[1])) {
            $rRule = $params[1];
        }
        return $rRule;
    }

    /**
     * @param array $params
     * @return User
     * @throws \common_exception_InconsistentData
     */
    private function getUser(array $params)
    {
        if (!isset($params[0]) || !\common_Utils::isUri($params[0])) {
            throw new \common_exception_InconsistentData(__('First parameter must be existing user\'s uri'));
        }

        $userResource = $this->getResource($params[0]);

        if (!$userResource->exists()) {
            throw new \common_exception_InconsistentData(__('User with given uri does not exist'));
        }

        $user = UserHelper::getUser($userResource->getUri());

        if (!AclProxy::hasAccess($user, \tao_actions_TaskQueueWebApi::class, 'getAll', [])) {
            throw new \common_exception_InconsistentData(__('User does not have access to the task queue rest API'));
        }

        return $user;
    }
}
