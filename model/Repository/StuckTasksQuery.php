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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\model\Repository;

use DateTimeImmutable;

class StuckTasksQuery
{
    private const MIN_AGE = 300;

    /** @var string */
    private $queryName;

    /** @var array */
    private $whitelist;

    /** @var int */
    private $age;

    public function __construct(string $queryName, array $whitelist, int $age)
    {
        array_walk(
            $whitelist,
            function (&$value) {
                $value = trim($value);
            }
        );

        $this->queryName = $queryName;
        $this->whitelist = $whitelist;
        $this->age = max($age, self::MIN_AGE);
    }

    public function getQueryName(): string
    {
        return $this->queryName;
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
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
