<?php
/**
 * This file is part of PHP_Reflection.
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
 * @category   PHP
 * @package    PHP_Reflection
 * @subpackage Ast
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Reflection/Ast/AbstractNode.php';
require_once 'PHP/Reflection/Ast/Iterator.php';
require_once 'PHP/Reflection/Ast/Iterator/TypeFilter.php';

/**
 * Represents a php package node.
 *
 * @category   PHP
 * @package    PHP_Reflection
 * @subpackage Ast
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Reflection_Ast_Package extends PHP_Reflection_Ast_AbstractNode
{
    /**
     * List of all {@link PHP_Reflection_Ast_ClassOrInterfaceI} objects for this package.
     *
     * @var array(PHP_Reflection_Ast_ClassOrInterfaceI) $types
     */
    protected $types = array();
    
    /**
     * List of all standalone {@link PHP_Reflection_Ast_Function} objects in this
     * package.
     *
     * @var array(PHP_Reflection_Ast_Function) $functions
     */
    protected $functions = array();
    
    /**
     * Returns an iterator with all {@link PHP_Reflection_Ast_Class} instances
     * within this package.
     *
     * @return PHP_Reflection_Ast_Iterator
     */
    public function getClasses()
    {
        $type = 'PHP_Reflection_Ast_Class';
        
        $classes = new PHP_Reflection_Ast_Iterator($this->types);
        $classes->addFilter(new PHP_Reflection_Ast_Iterator_TypeFilter($type));
        
        return $classes;
    }
    
    /**
     * Returns an iterator with all {@link PHP_Reflection_Ast_Interface} instances
     * within this package.
     *
     * @return PHP_Reflection_Ast_Iterator
     */
    public function getInterfaces()
    {
        $type = 'PHP_Reflection_Ast_Interface';
        
        $classes = new PHP_Reflection_Ast_Iterator($this->types);
        $classes->addFilter(new PHP_Reflection_Ast_Iterator_TypeFilter($type));
        
        return $classes;
    }
    
    /**
     * Returns all {@link PHP_Reflection_Ast_ClassOrInterfaceI} objects in this package.
     *
     * @return PHP_Reflection_Ast_Iterator
     */
    public function getTypes()
    {
        return new PHP_Reflection_Ast_Iterator($this->types);
    }
    
    /**
     * Adds the given type to this package and returns the input type instance.
     *
     * @param PHP_Reflection_Ast_AbstractClassOrInterface $type The new package type.
     * 
     * @return PHP_Reflection_Ast_AbstractClassOrInterface
     */
    public function addType(PHP_Reflection_Ast_AbstractClassOrInterface $type)
    {
        // Skip if this package already contains this type
        if (in_array($type, $this->types, true)) {
            return;
        }
        
        if ($type->getPackage() !== null) {
            $type->getPackage()->removeType($type);
        }
        
        // Set this as class package
        $type->setPackage($this);
        // Append class to internal list
        $this->types[] = $type;
        
        return $type;
    }
    
    /**
     * Removes the given type instance from this package.
     *
     * @param PHP_Reflection_Ast_AbstractClassOrInterface $type The type instance to remove.
     * 
     * @return void
     */
    public function removeType(PHP_Reflection_Ast_AbstractClassOrInterface $type)
    {
        if (($i = array_search($type, $this->types, true)) !== false) {
            // Remove class from internal list
            unset($this->types[$i]);
            // Remove this as parent
            $type->setPackage(null);
        }
    }
    
    /**
     * Returns all {@link PHP_Reflection_Ast_Function} objects in this package.
     *
     * @return PHP_Reflection_Ast_Iterator
     */
    public function getFunctions()
    {
        return new PHP_Reflection_Ast_Iterator($this->functions);
    }
    
    /**
     * Adds the given function to this package and returns the input instance.
     *
     * @param PHP_Reflection_Ast_Function $function The new package function.
     * 
     * @return PHP_Reflection_Ast_Function
     */
    public function addFunction(PHP_Reflection_Ast_Function $function)
    {
        if ($function->getPackage() !== null) {
            $function->getPackage()->removeFunction($function);
        }

        // Set this as function package
        $function->setPackage($this);
        // Append function to internal list
        $this->functions[] = $function;
        
        return $function;
    }
    
    /**
     * Removes the given function from this package.
     *
     * @param PHP_Reflection_Ast_Function $function The function to remove
     * 
     * @return void
     */
    public function removeFunction(PHP_Reflection_Ast_Function $function)
    {
        if (($i = array_search($function, $this->functions, true)) !== false) {
            // Remove function from internal list
            unset($this->functions[$i]);
            // Remove this as parent
            $function->setPackage(null);
        }
    }

    /**
     * Visitor method for node tree traversal.
     *
     * @param PHP_Reflection_VisitorI $visitor The context visitor implementation.
     * 
     * @return void
     */
    public function accept(PHP_Reflection_VisitorI $visitor)
    {
        $visitor->visitPackage($this);
    }
}