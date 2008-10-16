<?php
/**
 * This file is part of PHP_Depend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008, Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Metrics
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend/Metrics/AbstractAnalyzer.php';
require_once 'PHP/Depend/Metrics/AnalyzerI.php';
require_once 'PHP/Depend/Metrics/FilterAwareI.php';
require_once 'PHP/Depend/Metrics/NodeAwareI.php';
require_once 'PHP/Depend/Metrics/ProjectAwareI.php';

/**
 * This analyzer calculates class/package hierarchy metrics.
 * 
 * This analyzer expects that a node list filter is set, before it starts the 
 * analyze process. This filter will suppress PHP internal and external library
 * stuff.
 * 
 * This analyzer is based on the following metric set:
 * - http://www.aivosto.com/project/help/pm-oo-misc.html
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Metrics
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_Metrics_Hierarchy_Analyzer
       extends PHP_Depend_Metrics_AbstractAnalyzer
    implements PHP_Depend_Metrics_AnalyzerI,
               PHP_Depend_Metrics_FilterAwareI,
               PHP_Depend_Metrics_NodeAwareI,
               PHP_Depend_Metrics_ProjectAwareI
{
    /**
     * Number of all analyzed packages.
     * 
     * @type integer
     * @var integer $_pkgs
     */
    private $_pkgs = 0;
    
    /**
     * Number of all analyzed functions.
     *
     * @type integer
     * @var integer $_fcs
     */
    private $_fcs = 0;
    
    /**
     * Number of all analyzer methods.
     *
     * @type integer
     * @var integer $_mts
     */
    private $_mts = 0;
    
    /**
     * Number of all analyzed classes.
     *
     * @type integer
     * @var integer $_cls
     */
    private $_cls = 0;
    
    /**
     * Number of all analyzed abstract classes.
     *
     * @type integer
     * @var integer $_clsa
     */
    private $_clsa = 0;
    
    /**
     * Number of all analyzed interfaces.
     *
     * @type integer 
     * @var integer $_interfs
     */
    private $_interfs = 0;
    
    /**
     * Number of all root classes within the analyzed source code.
     *
     * @type integer
     * @var integer $_roots
     */
    private $_roots = 0;
    
    /**
     * Number of all leaf classes within the analyzed source code
     *
     * @type integer
     * @var integer $_leafs
     */
    private $_leafs = 0;
    
    /**
     * The maximum depth of inheritance tree value within the analyzed source code.
     *
     * @type integer
     * @var integer $_maxDIT
     */
    private $_maxDIT = 0;
    
    /**
     * Hash with all calculated node metrics.
     *
     * <code>
     * array(
     *     '0375e305-885a-4e91-8b5c-e25bda005438'  =>  array(
     *         'loc'    =>  42,
     *         'ncloc'  =>  17,
     *         'cc'     =>  12
     *     ),
     *     'e60c22f0-1a63-4c40-893e-ed3b35b84d0b'  =>  array(
     *         'loc'    =>  42,
     *         'ncloc'  =>  17,
     *         'cc'     =>  12
     *     )
     * )
     * </code>
     *
     * @type array<array>
     * @var array(string=>array) $_nodeMetrics
     */
    private $_nodeMetrics = null;
    
    /**
     * Processes all {@link PHP_Reflection_Ast_Package} code nodes.
     *
     * @param PHP_Reflection_Ast_Iterator $packages The input package set.
     * 
     * @return void
     * @see PHP_Depend_Metrics_AnalyzerI::analyze()
     */
    public function analyze(PHP_Reflection_Ast_Iterator $packages)
    {
        if ($this->_nodeMetrics === null) {
            
            $this->fireStartAnalyzer();
            
            // Init node metrics
            $this->_nodeMetrics = array();
            
            // Visit all nodes
            foreach ($packages as $package) {
                $package->accept($this);
            }
            
            $this->fireEndAnalyzer();
        }
    }
    
    /**
     * Provides the project summary metrics as an <b>array</b>.
     *
     * @return array(string=>mixed)
     */
    public function getProjectMetrics()
    {
        return array(
            'clsa'     =>  $this->_clsa,
            'clsc'     =>  $this->_cls - $this->_clsa,
            'roots'    =>  $this->_roots,
            'leafs'    =>  $this->_leafs,
            'maxDIT'   =>  $this->_maxDIT,
        );
    }
    
    /**
     * This method will return an <b>array</b> with all generated metric values 
     * for the given <b>$node</b> instance. If there are no metrics for the 
     * requested node, this method will return an empty <b>array</b>.
     *
     * @param PHP_Reflection_Ast_NodeI $node The context node instance.
     * 
     * @return array(string=>mixed)
     */
    public function getNodeMetrics(PHP_Reflection_Ast_NodeI $node)
    {
        if (isset($this->_nodeMetrics[$node->getUUID()])) {
            return $this->_nodeMetrics[$node->getUUID()];
        }
        return array();
    }
    
    /**
     * Calculates metrics for the given <b>$class</b> instance.
     *
     * @param PHP_Reflection_Ast_ClassI $class The context class instance.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitClass()
     */
    public function visitClass(PHP_Reflection_Ast_ClassI $class)
    {
        $this->fireStartClass($class);
        
        ++$this->_cls;
        
        if ($class->isAbstract()) {
            ++$this->_clsa;
        }
        
        if ($class->getChildClasses()->count() === 0) {
            ++$this->_leafs;
        } else if ($class->getParentClass() === null) {
            ++$this->_roots;
        }
        
        // Get class dit value
        $dit = $this->getClassDIT($class);
        // Store node metric
        $this->_nodeMetrics[$class->getUUID()] = array('dit'  =>  $dit);
        // Collect max dit value
        $this->_maxDIT = max($this->_maxDIT, $dit);
        
        foreach ($class->getMethods() as $method) {
            $method->accept($this);
        }
        foreach ($class->getProperties() as $property) {
            $property->accept($this);
        }
        
        $this->fireEndClass($class);
    }
    
    /**
     * Calculates metrics for the given <b>$function</b> instance.
     *
     * @param PHP_Reflection_Ast_Function $function The context function instance.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitFunction()
     */
    public function visitFunction(PHP_Reflection_Ast_FunctionI $function)
    {
        $this->fireStartFunction($function);
        ++$this->_fcs;
        $this->fireEndFunction($function);
    }
    
    /**
     * Calculates metrics for the given <b>$interface</b> instance.
     *
     * @param PHP_Reflection_Ast_InterfaceI $interface The context interface instance.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitInterface()
     */
    public function visitInterface(PHP_Reflection_Ast_InterfaceI $interface)
    {
        $this->fireStartInterface($interface);
        
        ++$this->_interfs;
        
        foreach ($interface->getMethods() as $method) {
            $method->accept($this);
        }
        
        $this->fireEndInterface($interface);
    }
    
    /**
     * Visits a method node. 
     *
     * @param PHP_Reflection_Ast_MethodI $method The method class node.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitMethod()
     */
    public function visitMethod(PHP_Reflection_Ast_MethodI $method)
    {
        $this->fireStartMethod($method);
        ++$this->_mts;
        $this->fireEndMethod($method);
    }
    
    /**
     * Calculates metrics for the given <b>$package</b> instance.
     *
     * @param PHP_Reflection_Ast_Package $package The context package instance.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitPackage()
     */
    public function visitPackage(PHP_Reflection_Ast_Package $package)
    {
        $this->fireStartPackage($package);
        
        ++$this->_pkgs;
        
        foreach ($package->getTypes() as $type) {
            $type->accept($this);
        }
        
        foreach ($package->getFunctions() as $function) {
            $function->accept($this);
        }
        
        $this->fireEndPackage($package);
    }
    
    /**
     * Returns the depth of inheritance tree value for the given class.
     *
     * @param PHP_Reflection_Ast_Class $class The context code class instance.
     * 
     * @return integer
     */
    protected function getClassDIT(PHP_Reflection_Ast_Class $class)
    {
        $dit = 0;
        while (($class = $class->getParentClass()) !== null) {
            ++$dit;
        }
        return $dit;
    }
}