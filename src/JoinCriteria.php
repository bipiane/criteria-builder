<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/01/2019
 * Time: 12:56
 */

namespace bipiane;

/**
 * Class JoinCriteria
 * @package bipiane
 */
class JoinCriteria
{
    /**
     * @var string
     */
    public $join;

    /**
     * @var string
     */
    public $alias;

    /**
     * JoinCriteria constructor.
     * @param string $join
     * @param string $alias
     */
    public function __construct($join, $alias)
    {
        $this->join = $join;
        $this->alias = $alias;
    }

    function __toString()
    {
        return "join: '{$this->join}' alias: '{$this->alias}'";
    }

    /**
     * @return string
     */
    public function getJoin()
    {
        return $this->join;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }
}
