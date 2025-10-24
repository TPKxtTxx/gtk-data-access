<?php


class WhereGroup implements SQLTextInterface, SQLWhereInterface
{
    public $clauses = [];
    public $logicalOperator;

    public function __construct($logicalOperator = 'AND', $clauses = [])
    {
        $this->logicalOperator = $logicalOperator;
        $this->clauses         = $clauses;


    }

    public function addWhereClause($clause) 
    { 
        return $this->addClause($clause);
    }

    /* RawWhereClasue|WhereClause|WhereGroup|BetweenClause */ 
    public function addClause($clause) 
    {
        $this->clauses[] = $clause;
    }
    public function serializeToQueryParameters(&$queryParameters)
    {
        $queryParameters['clauses'][]= serialize($this);
    }
    public function addGroup(/* WhereGroup|WhereGroup */ $group) {
        $this->clauses[] = $group;
    }

    public function serializeToQueryParameters(&$queryParameters)
    {
        $queryParameters['clauses'][]= serialize($this);
    }

    public function getSQLForSelectQuery($selectQuery, &$params) 
    {
        if (!is_array($this->clauses))
        {
            throw new Exception("Clauses must be an array on WhereGroup");
        }

        $sqlParts = [];
        
        foreach ($this->clauses as $clause) 
        {
            $sqlParts[] = ($clause instanceof WhereGroup) ? '(' . $clause->getSQLForSelectQuery($selectQuery, $params) . ')' : $clause->getSQLForSelectQuery($selectQuery, $params);
        }


        return implode(" {$this->logicalOperator} ", $sqlParts);
    }

    public function where($column, $operator = null, ...$values) 
    {
        $whereClause = null;

        if ($column instanceof WhereClause)
        {
            $whereClause = $column;
        }
        else if ($column instanceof WhereGroup)
        {
            $whereClause = $column;
        }
        else
        {
            $whereClause = new WhereClause($column, $operator, ...$values);
        }

        $this->clauses[] = $whereClause;

        return $this;
    }

    public function addWhere($column, $operator = null, ...$values)
    {
        return $this->where($column, $operator, ...$values);
    }
}
