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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\model\Repository;

use DateTimeImmutable;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use oat\tao\model\taskQueue\TaskLog;

class StuckTaskQuery
{
    private const MIN_AGE = 300;
    private const ALLOWED_STATUSES = [
        TaskLog::STATUS_ENQUEUED,
    ];

    /** @var string */
    private $queryName;

    /** @var array */
    private $whitelist;

    /** @var int */
    private $age;

    /** @var array */
    private $statuses;

    public function __construct(string $queryName, array $whitelist, array $statuses, int $age)
    {
        array_walk(
            $whitelist,
            function (&$value) {
                $value = trim($value);
            }
        );

        $whitelist = array_filter($whitelist);

        if (empty($whitelist)) {
            throw new InvalidArgument('Stuck tasks names white list cannot be empty');
        }

        if (count(array_intersect($statuses, self::ALLOWED_STATUSES)) !== count($statuses)) {
            throw new InvalidArgument(
                sprintf(
                    'Only allowed statuses "%s" for stuck tasks. Provided: "%s"',
                    implode(',', self::ALLOWED_STATUSES),
                    implode(',', $statuses)
                )
            );
        }

        $this->queryName = $queryName;
        $this->whitelist = $whitelist;
        $this->age = max($age, self::MIN_AGE);
        $this->statuses = $statuses;
    }

    public function getQueryName(): string
    {
        return $this->queryName;
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function getAge()
    {
        return $this->age;
    }

    public function getAgeDateTime(): DateTimeImmutable
    {
        $date = new DateTimeImmutable();
        $date->modify(sprintf('-%s seconds', $this->age));

        return $date;
    }
}
