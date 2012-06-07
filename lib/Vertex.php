<?php

class Vertex extends Layoutable{
    /**
     * do not change order - FIFO : first in, first out
     * 
     * @var int
     */
    const ORDER_FIFO = 0;
    
    /**
     * order by vertex ID
     * 
     * @var int
     * @see Vertex::getId()
     */
    const ORDER_ID = 1;
    
    /**
     * order by vertex degree
     * 
     * @var int
     * @see Vertex::getDegree()
     */
    const ORDER_DEGREE = 2;
    
    /**
     * order by indegree of vertex
     * 
     * @var int
     * @see Vertex::getDegreeIn()
     */
    const ORDER_INDEGREE = 3;
    
    /**
     * order by outdegree of vertex
     * 
     * @var int
     * @see Vertex::getDegreeOut()
     */
    const ORDER_OUTDEGREE = 4;
    
    /**
     * random/shuffled order
     * 
     * @var int
     */
    const ORDER_RANDOM = 5;
    
    /**
     * get first vertex (optionally ordered by given criterium $by) from given array of vertices
     *
     * @param array[Vertex]|Graph $vertices array of vertices to scan for 'first' vertex
     * @param int                 $by       criterium to sort by. see Vertex::ORDER_ID, etc.
     * @param boolean             $desc     whether to return biggest (true) instead of smallest (default:false)
     * @return Vertex
     * @throws Exception if criterium is unknown, no vertices exist or calling vertex functions throws an exception (degree on digraphs)
     * @uses Graph::getVertices() if graph is given instead of vertices
     * @uses Vertex::getId()
     * @uses Vertex::getDegree()
     * @uses Vertex::getDegreeIn()
     * @uses Vertex::getDegreeOut()
     */
    public static function getFirst($vertices,$by=self::ORDER_FIFO,$desc=false){
        if($vertices instanceof Graph){
            $vertices = $vertices->getVertices();
        }
        if($by === self::ORDER_RANDOM && $vertices){ // random order and there are actually some vertices to shuffle
            return $vertices[array_rand($vertices)]; // just return by random key (no need to check for DESC flag)
        }
        $ret = NULL;
        $best = NULL;
        foreach($vertices as $vertex){
            if($by === self::ORDER_FIFO){        // do not sort - needs special handling
                if($desc){            // always remember vertex from last iteration
                    $ret = $vertex;
                    continue;
                }else{                // just return first vertex right away
                    return $vertex;
                }
            }else if($by === self::ORDER_ID){
                $now = $vertex->getId();
            }else if($by === self::ORDER_DEGREE){
                $now = $vertex->getDegree();
            }else if($by === self::ORDER_INDEGREE){
                $now = $vertex->getDegreeIn();
            }else if($by === self::ORDER_OUTDEGREE){
                $now = $vertex->getDegreeOut();
            }else{
                throw new Exception('Invalid order flag "'.$by.'"');
            }
            if($ret === NULL || ($desc && $now > $best) || (!$desc && $now < $best)){
                $ret = $vertex;
                $best = $now;
            }
        }
        if($ret === NULL){
            throw new Exception('No vertex found');
        }
        return $ret;
    }
    
    /**
     * get iterator for vertices (optionally ordered by given criterium $by) from given array of vertices
     *
     * @param array[Vertex]|Graph $vertices array of vertices to scan for 'first' vertex
     * @param int                 $by       criterium to sort by. see Vertex::ORDER_ID, etc.
     * @param boolean             $desc     whether to return biggest first (true) instead of smallest first (default:false)
     * @return Iterator iterator object (supporting at the very least foreach)
     * @throws Exception if criterium is unknown or calling vertex functions throws an exception (degree on digraphs)
     * @uses Graph::getVertices() if graph is given instead of vertices
     * @uses Vertex::getId()
     * @uses Vertex::getDegree()
     * @uses Vertex::getDegreeIn()
     * @uses Vertex::getDegreeOut()
     */
    public static function getAll($vertices,$by=self::ORDER_FIFO,$desc=false){
        if($vertices instanceof Graph){
            $vertices = $vertices->getVertices();
        }
        if($by === self::ORDER_FIFO){
            return new ArrayIterator($desc ? array_reverse($vertices) : $vertices);
        }
        if($by === self::ORDER_RANDOM){
            shuffle($vertices);
            return new ArrayIterator($vertices); // create iterator for shuffled array (no need to check DESC flag)
        }
        $it = new SplPriorityQueue();
        foreach($vertices as $vertex){
            if($by === self::ORDER_ID){
                $now = $vertex->getId();
                if($desc && is_string($now)){
                    throw new Exception('Unable to reverse sorting for string IDs');
                }
            }else if($by === self::ORDER_DEGREE){
                $now = $vertex->getDegree();
            }else if($by === self::ORDER_INDEGREE){
                $now = $vertex->getDegreeIn();
            }else if($by === self::ORDER_OUTDEGREE){
                $now = $vertex->getDegreeOut();
            }else{
                throw new Exception('Invalid order flag "'.$by.'"');
            }
            if($desc && $now !== NULL){
                $now = -$now;
            }
            $it->insert($vertex,$now);
        }
        return $it;
    }
    
    private $id;
    
    /**
     * @var array[Edge]
     */
    private $edges = array();
    
    /**
     * @var Graph
     */
    private $graph;
    
    /**
     * vertex balance
     * 
     * @var float|NULL
     * @see Vertex::setBalance()
     */
    private $balance;
    
    /**
     * Creates a Vertex
     * 
     * @param int   $id    Identifier (int, string, what you want) $id
     * @param Graph $graph graph to be added to
     */
    public function __construct($id, $graph){
        $this->id = $id;
        $this->graph = $graph;
    }
    
    /**
     * get graph this vertex is attached to
     * 
     * @return Graph
     */
    public function getGraph(){
        return $this->graph;
    }
    
    public function getBalance(){
        return $this->balance;
    }
    
    public function setBalance($balance){
        if($balance !== NULL && !is_float($balance) && !is_int($balance)){
            throw new Exception();
        }
        $this->balance = $balance;
        return $this;
    }
    
    /**
     * Calculates the flow for this Vertex: sum(inflow) - sum(outflow)
     * 
     * Usually, vertices should have a resulting flow of 0: The sum of flows
     * entering a vertex must equal the sum of flows leaving a vertex. If the
     * resulting flow is > 0, this vertex is considered a sink (i.e. there's
     * too much flow into this vertex). If the resulting flow is < 0, this
     * vertex is considered a "source" (i.e. there's more flow leaving this
     * vertex).
     * 
     * @return float
     * @throws Exception if they are undirected edges
     * @see Vertex::getBalance()
     */
    public function getFlow(){
        $sumOfFlow = 0;
    
        foreach ($this->edges as $edge){
            if ( ! ($edge instanceof EdgeDirected)){
                throw new Exception("TODO: undirected edges not suported yet");
            }
            
            if ($edge->hasVertexStart($this)){ // edge is an outgoing edge of this vertex
                $sumOfFlow -= $edge->getFlow(); // flowing out (flow is "pointing away")
            }
            else{                                // this is an ingoing edge
                $sumOfFlow += $edge->getFlow(); // flowing in
            }
        }
        
        return $sumOfFlow;
    }
    
    /**
     * Return string with vertex visualisation
     *
     * @return string
     */
    public function toString(){
        $return = "Edges of vertex ".$this->id.":\n";
        
        foreach ($this->edges as $edge){
            $return .= "\t".$edge->toString()."\n"; 
        }
        
        return $return;
    }
    
//getter setter
    
    /**
     * returns id of this Vertex
     * 
     * @return int
     */
    public function getId(){
        return $this->id;
    }
    
    /**
     * checks whether this start vertex has a path to the given target vertex
     * 
     * @param Vertex $vertex
     * @return boolean
     * @uses AlgorithmSpBreadthFirst::hasVertex()
     */
    public function hasPathTo($vertex){
        $alg = new AlgorithmSpBreadthFirst($this);
        return $alg->hasVertex($vertex);
    }
    
    /**
     * checks whether the given vertex has a path TO THIS vertex
     * 
     * @param Vertex $vertex
     * @return boolean
     * @uses Vertex::hasPathTo()
     */
    public function hasPathFrom($vertex){
        return $vertex->hasPathTo($this);
    }
    
    /**
     * get array of vertices this vertex has a path to
     * 
     * @return array[Vertex]
     * @uses AlgorithmSpBreadthFirst::getVertices()
     */
    public function getVerticesPathTo(){
        $alg = new AlgorithmSpBreadthFirst($this);
        return $alg->getVertices();
    }
    
    /**
     * get array of vertices that have a path to this vertex
     * 
     * @return array[Vertex]
     * @uses AlgorithmSpBreadthFirst::getVertices()
     */
    public function getVerticesPathFrom(){
        $alg = new AlgorithmSpBreadthFirst($this,true);
        return $alg->getVertices();
    }
    
    /**
     * create new directed edge from this start vertex to given target vertex
     * 
     * @param Vertex $vertex target vertex
     * @return EdgeDirected
     * @throws Exception
     * @uses Graph::addEdge()
     */
    public function createEdgeTo($vertex){
        if($vertex->getGraph() !== $this->graph){
            throw new Exception('Target vertex has to be within the same graph');
        }
        
        $edge = new EdgeDirected($this,$vertex);
        $this->edges []= $edge;
        $vertex->edges []= $edge;
        $this->graph->addEdge($edge);
        return $edge;
    }
    
    /**
     * add new undirected (bidirectional) edge between this vertex and given vertex
     * 
     * @param Vertex $vertex
     * @return EdgeUndirected
     * @throws Exception
     * @uses Graph::addEdge()
     */
    public function createEdge($vertex){
        if($vertex->getGraph() !== $this->graph){
            throw new Exception('Target vertex has to be within the same graph');
        }
        
        $edge = new EdgeUndirectedId($this,$vertex);
        $this->edges []= $edge;
        $vertex->edges []= $edge;
        $this->graph->addEdge($edge);
        return $edge;
    }
    
    /**
     * remove the given edge from list of connected edges (MUST NOT be called manually)
     * 
     * @param Edge $edge
     * @return void
     * @private
     * @see Edge::destroy() instead!
     */
    public function removeEdge($edge){
        $id = array_search($edge,$this->edges,true);
        if($id === false){
            throw new Exception('Given edge does NOT exist');                //Tobias: if edge gets Id => output of id
        }
        unset($this->edges[$id]);
    }
    
    /**
     * check whether this vertex has a direct edge to given $vertex
     * 
     * @param Vertex $vertex
     * @return boolean
     * @uses Edge::hasVertexTarget()
     */
    public function hasEdgeTo($vertex){
        foreach($this->edges as $edge){
            if($edge->isConnection($this, $vertex)){
                return true;
            }
        }
        return false;
    }
    
    /**
     * check whether the given vertex has a direct edge to THIS vertex
     * 
     * @param Vertex $vertex
     * @return boolean
     * @uses Vertex::hasEdgeTo()
     */
    public function hasEdgeFrom($vertex){
        return $vertex->hasEdgeTo($this);
    }
    
    /**
     * get ALL edges attached to this vertex
     * 
     * @return array[Edge]
     */
    public function getEdges(){
        return $this->edges;
    }
    
    /**
     * get ALL outgoing edges attached to this vertex
     *
     * @return array[Edge]
     */
    public function getEdgesOut(){
        $outgoingEdges = array();
        foreach ($this->edges as $edge){
            if ($edge->hasVertexStart($this)){
                $outgoingEdges[] = $edge;
            }
        }
        return $outgoingEdges;
    }
    
    /**
     * get ALL ingoing edges attached to this vertex
     *
     * @return array[Edge]
     */
    public function getEdgesIn(){
        $ingoingEdges = array() ;
        foreach ($this->edges as $edge){
            if ($edge->hasVertexTarget($this)){
                $ingoingEdges[] = $edge;
            }
        }
        return $ingoingEdges;
    }
    
    /**
     * get edges FROM this vertex TO the given vertex
     * 
     * @param Vertex $vertex
     * @return array[Edge]
     * @uses Edge::hasVertexTarget()
     */
    public function getEdgesTo($vertex){
        $ret = array();
        foreach($this->edges as $edge){
            if($edge->isConnection($this, $vertex)){
                $ret[] = $edge;
            }
        }
        return $ret;
    }
    
    /**
     * get edges FROM the given vertex TO this vertex
     *
     * @param Vertex $vertex
     * @return array[Edge]
     * @uses Vertex::getEdgesTo()
     */
    public function getEdgesFrom($vertex){
        return $vertex->getEdgesTo($this);
    }
    
    /**
     * get all adjacent vertices of this vertex (edge FROM or TO this vertex)
     *  
     * @return array[Vertex]
     * @uses Edge::hasVertexStart()
     * @uses Edge::getVerticesToFrom()
     * @uses Edge::getVerticesFromTo()
     */
    public function getVerticesEdge(){
        $ret = array();
        foreach($this->edges as $edge){
            if($edge->hasVertexStart($this)){
                $vertex = $edge->getVertexToFrom($this);
            }else{
                $vertex = $edge->getVertexFromTo($this);
            }
            $ret[$vertex->getId()] = $vertex;
        }
        return $ret;
    }
    
    /**
     * get all vertices this vertex has an edge to
     * 
     * @return array[Vertex]
     * @uses Vertex::getEdgesOut()
     * @uses Edge::getVerticesToFrom()
     */
    public function getVerticesEdgeTo(){
        $ret = array();
        foreach($this->getEdgesOut() as $edge){
            $vertex = $edge->getVertexToFrom($this);
            $ret[$vertex->getId()] = $vertex;
        }
        return $ret;
    }
    
    /**
     * get all vertices that have an edge TO this vertex
     * 
     * @return array[Vertex]
     * @uses Vertex::getEdgesIn()
     * @uses Edge::getVerticesFromTo()
     */
    public function getVerticesEdgeFrom(){
        $ret = array();
        foreach($this->getEdgesIn() as $edge){
            $vertex = $edge->getVertexFromTo($this);
            $ret[$vertex->getId()] = $vertex;
        }
        return $ret;
    }
    
    /**
     * get degree of this vertex (total number of edges)
     * 
     * vertex degree counts the total number of edges attached to this vertex
     * regardless of whether they're directed or not. loop edges are counted
     * twice as both start and end form a 'line' to the same vertex.
     * 
     * @return int
     * @see Vertex::getDegreeIn()
     * @see Vertex::getDegreeOut()
     */
    public function getDegree(){
        return count($this->edges);
    }
    
    /**
     * check whether this vertex is isolated (i.e. has no edges attached)
     * 
     * @return boolean
     */
    public function isIsolated(){
        return !$this->edges;
    }
    
    /**
     * check whether this is a leaf node (i.e. has only one edge)
     * 
     * @return boolean
     * @throws Exception if this is directed graph
     * @uses Vertex::getDegree()
     * @todo check logic! should be indegree=1 and outdegree=0 for directed and degree=indegree=outdegree=1 for undirected?
     */
    public function isLeaf(){
        return ($this->getDegree() === 1);
    }
    
    /**
     * get indegree of this vertex (number of edges TO this vertex)
     * 
     * @return int
     * @uses Edge::hasVertexTarget()
     * @see Vertex::getDegree()
     */
    public function getDegreeIn(){
        $n = 0;
        foreach($this->edges as $edge){
            if($edge->hasVertexTarget($this)){
                ++$n;
            }
        }
        return $n;
    }
    
    /**
     * get outdegree of this vertex (number of edges FROM this vertex TO other vertices)
     * 
     * @return int
     * @uses Edge::hasVertexStart()
     * @see Vertex::getDegree()
     */
    public function getDegreeOut(){
        $n = 0;
        foreach($this->edges as $edge){
            if($edge->hasVertexStart($this)){
                ++$n;
            }
        }
        return $n;
    }
    
    /**
     * checks whether this vertex is a source, i.e. its indegree is zero
     * 
     * @return boolean
     * @uses Edge::hasVertexTarget()
     * @see Vertex::getDegreeIn()
     */
    public function isSource(){
        foreach($this->edges as $edge){
            if($edge->hasVertexTarget($this)){
                return false;
            }
        }
        return true; // reach this point: no edge to this vertex
    }
    
    /**
     * checks whether this vertex is a sink, i.e. its outdegree is zero
     * 
     * @return boolean
     * @uses Edge::hasVertexStart()
     * @see Vertex::getDegreeOut()
     */
    public function isSink(){
        foreach($this->edges as $edge){
            if($edge->hasVertexStart($this)){
                return false;
            }
        }
        return true; // reach this point: no edge away from this vertex
    }
    
    /**
     * checks whether this vertex has a loop (edge to itself)
     * 
     * @return boolean
     * @uses Edge::isLoop()
     */
    public function hasLoop(){
        foreach($this->edges as $edge){
            if($edge->isLoop()){
                return true;
            }
        }
        return false;
    }
    
    /**
     * destroy vertex and all edges connected to it and remove reference from graph
     *
     * @uses Edge::destroy()
     * @uses Graph::removeVertex()
     */
    public function destroy(){
        foreach($this->edges as $edge){
            $edge->destroy();
        }
        $this->graph->removeVertex($this);
    }
    
    /**
     * do NOT allow cloning of objects
     *
     * @throws Exception
     */
    private function __clone(){
        throw new Exception();
    }
}
