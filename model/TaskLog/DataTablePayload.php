<?php

namespace oat\taoTaskQueue\model\TaskLog;

use oat\tao\model\datatable\DatatablePayload as DataTablePayloadInterface;
use oat\tao\model\datatable\implementation\DatatableRequest;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;

class DataTablePayload implements DataTablePayloadInterface, \Countable
{
    private $taskLogFilter;
    private $broker;
    private $request;

    /**
     * @var \Closure
     */
    private $rowCustomiser;

    /**
     * DataTablePayload constructor.
     *
     * @param TaskLogFilter                  $filter
     * @param TaskLogBrokerInterface         $broker
     */
    public function __construct(TaskLogFilter $filter, TaskLogBrokerInterface $broker)
    {
        $this->taskLogFilter = $filter;
        $this->broker = $broker;
        $this->request = DatatableRequest::fromGlobals();

        $this->applyDataTableFilters();
    }

    /**
     * You can pass an anonymous function to customise the final payload: either to change the value of a field or to add extra field(s);
     *
     * The function will be bind to the task log entity (TaskLogEntity) so $this can be used inside of the closure.
     * The return value needs to be an array.
     *
     * For example:
     * <code>
     *  $payload->customiseRowBy(function (){
     *      $row['extraField'] = 'value';
     *      $row['extraField2'] = $this->getParameters()['some_parameter_key'];
     *      $row['createdAt'] = \tao_helpers_Date::displayeDate($this->getCreatedAt());
     *
     *      return $row;
     *  });
     * </code>
     *
     * @param \Closure $func
     * @return DataTablePayload
     */
    public function customiseRowBy(\Closure $func)
    {
        $this->rowCustomiser = $func;

        return $this;
    }

    /**
     * @return array
     */
    public function getPayload()
    {
        $countTotal = $this->count();

        $page = $this->request->getPage();
        $limit = $this->request->getRows();

        // we don't want to "pollute" the original filter
        $cloneFilter = clone $this->taskLogFilter;

        $cloneFilter->setLimit($limit)
            ->setOffset($limit * ($page - 1))
            ->setSortBy($this->request->getSortBy())
            ->setSortOrder($this->request->getSortOrder());

        // get task log entities by filters
        $collection = $this->broker->search($cloneFilter);

        $resultData = [];

        foreach ($collection as $taskLogEntity) {
            $newCustomiser = $this->rowCustomiser->bindTo($taskLogEntity, $taskLogEntity);

            $resultData[] = array_merge($taskLogEntity->toArray(), (array) $newCustomiser());
        }

        $data = [
            'rows'    => $limit,
            'page'    => $page,
            'amount'  => count($collection),
            'total'   => ceil($countTotal / $limit),
            'data'    => $resultData,
        ];

        return $data;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->broker->count($this->taskLogFilter);
    }

    /**
     * Add filter values from request to the taskLogFilter.
     */
    private function applyDataTableFilters()
    {
        $filters = $this->request->getFilters();

        foreach ($filters as $fieldName => $filterValue) {
            if (empty($filterValue)) {
                continue;
            }

            if (is_array($filterValue)) {
                $this->taskLogFilter->in($fieldName, $filterValue);
                continue;
            }

            $this->taskLogFilter->eq($fieldName, (string) $filterValue);
        }
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getPayload();
    }
}