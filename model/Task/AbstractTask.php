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

namespace oat\taoTaskQueue\model\Task;

/**
 * Class AbstractTask
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
abstract class AbstractTask implements TaskInterface
{
    private $metadata = [];
    private $parameters = [];

    /**
     * @inheritdoc
     */
    public function __construct($id, $owner)
    {
        $this->setMetadata(self::JSON_METADATA_ID_KEY, $id);
        $this->setCreatedAt(new \DateTime());
        $this->setOwner($owner);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'TASK '. get_called_class() .' ['. $this->getId() .']';
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getMetadata(self::JSON_METADATA_ID_KEY);
    }

    /**
     * Set metadata
     *
     * @param  string|array|\Traversable $spec
     * @param  mixed $value
     * @throws \InvalidArgumentException
     * @return TaskInterface
     */
    public function setMetadata($spec, $value = null)
    {
        if (is_string($spec)) {
            $this->metadata[$spec] = $value;
            return $this;
        }

        if (!is_array($spec) && !$spec instanceof \Traversable) {
            throw new \InvalidArgumentException(sprintf(
                'Expected a string, array, or Traversable argument in first position; received "%s"',
                (is_object($spec) ? get_class($spec) : gettype($spec))
            ));
        }

        foreach ($spec as $key => $value) {
            $this->metadata[$key] = $value;
        }

        return $this;
    }

    /**
     * Retrieve a single metadata as specified by key
     *
     * @param  string $key
     * @param  null|mixed $default
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function getMetadata($key, $default = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException('Non-string argument provided as a metadata key');
        }

        if (array_key_exists($key, $this->metadata)) {
            return $this->metadata[$key];
        }

        return $default;
    }

    /**
     * Set task parameter
     *
     * @param  string|array|\Traversable $spec
     * @param  mixed $value
     * @throws \InvalidArgumentException
     * @return TaskInterface
     */
    public function setParameter($spec, $value = null)
    {
        if (is_string($spec)) {
            $this->parameters[$spec] = $value;
            return $this;
        }

        if (!is_array($spec) && !$spec instanceof \Traversable) {
            throw new \InvalidArgumentException(sprintf(
                'Expected a string, array, or Traversable argument in first position; received "%s"',
                (is_object($spec) ? get_class($spec) : gettype($spec))
            ));
        }

        foreach ($spec as $key => $value) {
            $this->parameters[$key] = $value;
        }

        return $this;
    }

    /**
     * Retrieve a single parameter as specified by key
     *
     * @param  string $key
     * @param  null|mixed $default
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function getParameter($key, $default = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException('Non-string argument provided as a parameter key');
        }

        if (array_key_exists($key, $this->parameters)) {
            return $this->parameters[$key];
        }

        return $default;
    }

    /**
     * @inheritdoc
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param \DateTime $dateTime
     * @return TaskInterface
     */
    public function setCreatedAt(\DateTime $dateTime)
    {
        $this->setMetadata('__created_at__', $dateTime);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        if (($date = $this->getMetadata('__created_at__')) && is_string($date)) {
            $date = (new \DateTime($date));
        }

        return $date;
    }

    /**
     * @param string $owner
     * @return TaskInterface
     */
    public function setOwner($owner)
    {
        $this->setMetadata(self::JSON_METADATA_OWNER_KEY, (string) $owner);

        return $this;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->getMetadata(self::JSON_METADATA_OWNER_KEY);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        // use ISO 8601 format for serializing date
        $this->setMetadata('__created_at__', $this->getCreatedAt()->format('c'));

        return [
            self::JSON_TASK_CLASS_NAME_KEY => get_called_class(),
            self::JSON_METADATA_KEY => $this->metadata,
            self::JSON_PARAMETERS_KEY => $this->getParameters()
        ];
    }
}
