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
 * @subpackage Log
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend/Log/LoggerI.php';
require_once 'PHP/Depend/Log/CodeAwareI.php';
require_once 'PHP/Depend/Log/FileAwareI.php';
require_once 'PHP/Depend/Log/NoLogOutputException.php';
require_once 'PHP/Depend/Metrics/NodeAwareI.php';
require_once 'PHP/Depend/Metrics/ProjectAwareI.php';
// TODO: Refactory this reflection dependency
require_once 'PHP/Reflection/Visitor/AbstractVisitor.php';

/**
 * This logger provides a xml log file, that is compatible with the files 
 * generated by the <a href="http://www.phpunit.de">PHPUnit</a> --log-metrics
 * option.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Log
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_Log_Phpunit_Xml
       extends PHP_Reflection_Visitor_AbstractVisitor
    implements PHP_Depend_Log_LoggerI,
               PHP_Depend_Log_CodeAwareI,
               PHP_Depend_Log_FileAwareI
{
    /**
     * The log output file.
     *
     * @type string
     * @var string $_logFile
     */
    private $_logFile = null;
    
    /**
     * The raw {@link PHP_Reflection_Ast_Package} instances.
     *
     * @type PHP_Reflection_Ast_Iterator
     * @var PHP_Reflection_Ast_Iterator $code
     */
    protected $code = null;
    
    /**
     * List of all generated project metrics.
     *
     * @type array<mixed>
     * @var array(string=>mixed) $projectMetrics
     */
    protected $projectMetrics = array();
    
    /**
     * List of all analyzers that implement the node aware interface
     * {@link PHP_Depend_Metrics_NodeAwareI}.
     *
     * @type array<PHP_Depend_Metrics_AnalyzerI>
     * @var array(PHP_Depend_Metrics_AnalyzerI) $_nodeAwareAnalyzers
     */
    private $_nodeAwareAnalyzers = array();
    
    /**
     * The internal used xml stack.
     *
     * @type array<DOMElement>
     * @var array(DOMElement) $_xmlStack
     */
    private $_xmlStack = array();
    
    /**
     * Number of visited files.
     *
     * @type integer
     * @var integer $_files
     */
    private $_files = 0;
    
    /**
     * This property contains some additional metrics for the file-DOMElement.
     *
     * @type array<integer>
     * @var array(string=>integer) $_additionalFileMetrics
     */
    private $_additionalFileMetrics = array(
        'classes'    =>  0,
        'functions'  =>  0
    );
    
    /**
     * This translation table maps some PHP_Depend identifiers with the 
     * corresponding PHPUnit identifiers.
     *
     * @type array<string>
     * @var array(string=>string)
     */
    private $_phpunitTranslationTable = array(
        'ccn2'    =>  'ccn',
        'noc'     =>  'classes',
        'noi'     =>  'interfs',
        'nof'     =>  'functions',
        'eloc'    =>  'locExecutable',
        'maxDIT'  =>  'maxdit',
    );
    
    /**
     * Returns an <b>array</b> with accepted analyzer types. These types can be
     * concrete analyzer classes or one of the descriptive analyzer interfaces. 
     *
     * @return array(string)
     */
    public function getAcceptedAnalyzers()
    {
        return array(
            'PHP_Depend_Metrics_NodeAwareI',
            'PHP_Depend_Metrics_ProjectAwareI'
        );
    }
    
    /**
     * Sets the output log file.
     *
     * @param string $logFile The output log file.
     * 
     * @return void
     */
    public function setLogFile($logFile)
    {
        $this->_logFile = $logFile;
    }
    
    /**
     * Sets the context code nodes.
     *
     * @param PHP_Reflection_Ast_Iterator $code The code nodes.
     * 
     * @return void
     */
    public function setCode(PHP_Reflection_Ast_Iterator $code)
    {
        $this->code = $code;
    }
    
    /**
     * Adds an analyzer to log. If this logger accepts the given analyzer it
     * with return <b>true</b>, otherwise the return value is <b>false</b>.
     *
     * @param PHP_Depend_Metrics_AnalyzerI $analyzer The analyzer to log.
     * 
     * @return boolean
     */
    public function log(PHP_Depend_Metrics_AnalyzerI $analyzer)
    {
        $accept = false;
        
        if ($analyzer instanceof PHP_Depend_Metrics_ProjectAwareI) {
            // Get project metrics
            $metrics = $analyzer->getProjectMetrics();
            // Merge with existing metrics.
            $this->projectMetrics = array_merge($this->projectMetrics, $metrics);
            
            $accept = true;
        }
        if ($analyzer instanceof PHP_Depend_Metrics_NodeAwareI) {
            $this->_nodeAwareAnalyzers[] = $analyzer;
            
            $accept = true;
        }
        
        return $accept;
    }
    
    /**
     * Closes the logger process and writes the output file.
     *
     * @return void
     * @throws PHP_Depend_Log_NoLogOutputException If the no log target exists.
     */
    public function close()
    {
        if ($this->_logFile === null) {
            throw new PHP_Depend_Log_NoLogOutputException($this);
        }
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        
        $dom->formatOutput = true;
        
        // Using XPath is only possible, when we add it to the document!?!?
        // Is this this correct?
        $metricsXml = $dom->appendChild($dom->createElement('metrics'));
        
        array_push($this->_xmlStack, $metricsXml);
        
        foreach ($this->code as $node) {
            $node->accept($this);
        }
        
        // Create project metrics and apply phpunit translation table
        $metrics = array_merge($this->projectMetrics, array(
            'files'  =>  $this->_files
        ));
        $metrics = $this->_applyPHPUnitTranslationTable($metrics);
        
        // Sort metrics
        ksort($metrics);
        
        // Append project metrics
        foreach ($metrics as $name => $value) {
            $metricsXml->setAttribute($name, $value);
        }
        
        $dom->save($this->_logFile);
    }
    
    /**
     * Visits a class node. 
     *
     * @param PHP_Reflection_Ast_ClassI $class The current class node.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitClass()
     */
    public function visitClass(PHP_Reflection_Ast_ClassI $class)
    {
        $this->_visitType($class);
    }
    
    /**
     * Visits a file node. 
     *
     * @param PHP_Reflection_Ast_File $file The current file node.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitFile()
     */
    public function visitFile(PHP_Reflection_Ast_File $file)
    {
        $metricsXml = end($this->_xmlStack);
        $document   = $metricsXml->ownerDocument;

        $xpath  = new DOMXPath($document);
        $result = $xpath->query("/metrics/file[@name='{$file->getFileName()}']");

        // Only add a new file
        if ($result->length === 0) {
            // Create a new file element
            $fileXml = $document->createElement('file');
            // Set source file name
            $fileXml->setAttribute('name', $file->getFileName());
        
            // Append all metrics
            $this->_appendMetrics($fileXml, $file, $this->_additionalFileMetrics);
            
            // Append file to metrics xml
            $metricsXml->appendChild($fileXml);

            // Update project file counter
            ++$this->_files;
        } else {
            $fileXml = $result->item(0);
        }
        
        // Add file to stack
        array_push($this->_xmlStack, $fileXml);
    }
    
    /**
     * Visits a function node. 
     *
     * @param PHP_Reflection_Ast_Function $function The current function node.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitFunction()
     */
    public function visitFunction(PHP_Reflection_Ast_FunctionI $function)
    {
        // First visit function file
        $function->getSourceFile()->accept($this);
        
        $fileXml  = end($this->_xmlStack);
        $document = $fileXml->ownerDocument;
        
        $functionXml = $document->createElement('function');
        $functionXml->setAttribute('name', $function->getName());
            
        $this->_appendMetrics($functionXml, $function);
            
        $fileXml->appendChild($functionXml);
        
        // Update file element @functions count
        $fileXml->setAttribute('functions', 1 + $fileXml->getAttribute('functions'));
        
        // Remove xml file element
        array_pop($this->_xmlStack);
    }
    
    /**
     * Visits a code interface object.
     *
     * @param PHP_Reflection_Ast_InterfaceI $interface The context code interface.
     * 
     * @return void
     * @see PHP_Reflection_VisitorI::visitInterface()
     */
    public function visitInterface(PHP_Reflection_Ast_InterfaceI $interface)
    {
        $this->_visitType($interface);
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
        $classXml = end($this->_xmlStack);
        $document = $classXml->ownerDocument;
        
        $methodXml = $document->createElement('method');
        $methodXml->setAttribute('name', $method->getName());
            
        $this->_appendMetrics($methodXml, $method);
            
        $classXml->appendChild($methodXml);
    }
    
    /**
     * Generic visit method for classes and interfaces.
     *
     * @param PHP_Reflection_Ast_ClassOrInterfaceI $type The context type.
     * 
     * @return void
     */
    private function _visitType(PHP_Reflection_Ast_ClassOrInterfaceI $type)
    {
        $type->getSourceFile()->accept($this);
        
        $fileXml  = end($this->_xmlStack);
        $document = $fileXml->ownerDocument;
        
        $classXml = $document->createElement('class');
        $classXml->setAttribute('name', $type->getName());
            
        $this->_appendMetrics($classXml, $type);
            
        $fileXml->appendChild($classXml);
        
        array_push($this->_xmlStack, $classXml);
        
        foreach ($type->getMethods() as $method) {
            $method->accept($this);
        }
        
        // Update file element @classes count
        $fileXml->setAttribute('classes', 1 + $fileXml->getAttribute('classes'));
        
        // Remove xml class element
        array_pop($this->_xmlStack);
        // Remove xml file element
        array_pop($this->_xmlStack);
    }
    
    /**
     * Aggregates all metrics for the given <b>$node</b> instance and adds them
     * to the <b>DOMElement</b>
     *
     * @param DOMElement            $xml     DOM Element that represents $node.
     * @param PHP_Reflection_Ast_NodeI $node    The context code node instance.
     * @param array(string=>mixed)  $metrics Set of additional node metrics
     * 
     * @return void
     */
    private function _appendMetrics(DOMElement $xml, 
                                    PHP_Reflection_Ast_NodeI $node,
                                    array $metrics = array())
    {
        // Collect all node metrics
        foreach ($this->_nodeAwareAnalyzers as $analyzer) {
            $metrics = array_merge($metrics, $analyzer->getNodeMetrics($node));
        }
        
        // Apply phpunit translation table
        $metrics = $this->_applyPHPUnitTranslationTable($metrics);
        
        // Sort result
        ksort($metrics);
        
        foreach ($metrics as $name => $value) {
            $xml->setAttribute($name, $value);
        }
    }
    
    /**
     * Translates PHP_Depend metric names into PHPUnit names.
     *
     * @param array(string=>mixed) $metrics Set of collected metric values.
     * 
     * @return array(string=>mixed)
     */
    private function _applyPHPUnitTranslationTable(array $metrics)
    {
        // Apply phpunit translation table
        foreach ($this->_phpunitTranslationTable as $pdepend => $phpunit) {
            // Skip unknown entries
            if (!isset($metrics[$pdepend])) {
                continue;
            }
            
            // Apply metric under phpunit identifier
            $metrics[$phpunit] = $metrics[$pdepend];
            // Remove metric with pdepend identifier
            unset($metrics[$pdepend]); 
        }
        return $metrics;
    }
}