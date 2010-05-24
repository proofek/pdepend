<?php
/**
 * This file is part of PHP_Depend.
 *
 * PHP Version 5
 *
 * Copyright (c) 2008-2010, Manuel Pichler <mapi@pdepend.org>.
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
 * @copyright  2008-2010 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://pdepend.org/
 */

require_once 'PHP/Depend/Metrics/AbstractAnalyzer.php';
require_once 'PHP/Depend/Metrics/AggregateAnalyzerI.php';
require_once 'PHP/Depend/Metrics/FilterAwareI.php';
require_once 'PHP/Depend/Metrics/NodeAwareI.php';
require_once 'PHP/Depend/Metrics/CyclomaticComplexity/Analyzer.php';

/**
 * Generates some class level based metrics. This analyzer is based on the
 * metrics specified in the following document.
 *
 * http://www.aivosto.com/project/help/pm-oo-misc.html
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Metrics
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008-2010 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://pdepend.org/
 */
class PHP_Depend_Metrics_ClassLevel_Analyzer
       extends PHP_Depend_Metrics_AbstractAnalyzer
    implements PHP_Depend_Metrics_AggregateAnalyzerI,
               PHP_Depend_Metrics_FilterAwareI,
               PHP_Depend_Metrics_NodeAwareI
{
    /**
     * Type of this analyzer class.
     */
    const CLAZZ = __CLASS__;

    /**
     * Metrics provided by the analyzer implementation.
     */
    const M_IMPLEMENTED_INTERFACES       = 'impl',
          M_CLASS_INTERFACE_SIZE         = 'cis',
          M_CLASS_SIZE                   = 'csz',
          M_PROPERTIES                   = 'vars',
          M_PROPERTIES_INHERIT           = 'varsi',
          M_PROPERTIES_NON_PRIVATE       = 'varsnp',
          M_WEIGHTED_METHODS             = 'wmc',
          M_WEIGHTED_METHODS_INHERIT     = 'wmci',
          M_WEIGHTED_METHODS_NON_PRIVATE = 'wmcnp',
          M_NUMBER_OF_PUBLIC_METHODS     = 'nopm',
          M_BASE_CLASS_OVERRIDING_RATIO  = 'bovr',
          M_BASE_CLASS_USAGE_RATIO       = 'bur',
          M_PNAS                         = 'pnas',
          M_NUMBER_OF_NEW_SERVICES       = 'nnas',
          M_NUMBER_OF_PROTECTED_MEMBERS  = 'nprotm';


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
     * @var array(string=>array) $_nodeMetrics
     */
    private $_nodeMetrics = null;

    /**
     * count of used protected members from the base class for each class
     *
     * <code>
     * array(
     *     '0375e305-885a-4e91-8b5c-e25bda005438'  => 20
     * )
     * </code>
     *
     * @var array(string=>array)
     */
    private $_baseClassUsageCount = null;

    private $_publicMethods = array();

    /**
     * The internal used cyclomatic complexity analyzer.
     *
     * @var PHP_Depend_Metrics_CyclomaticComplexity_Analyzer $_cyclomaticAnalyzer
     */
    private $_cyclomaticAnalyzer = null;

    /**
     * Processes all {@link PHP_Depend_Code_Package} code nodes.
     *
     * @param PHP_Depend_Code_NodeIterator $packages All code packages.
     *
     * @return void
     */
    public function analyze(PHP_Depend_Code_NodeIterator $packages)
    {
        if ($this->_nodeMetrics === null) {
            // First check for the require cc analyzer
            if ($this->_cyclomaticAnalyzer === null) {
                throw new RuntimeException('Missing required CC analyzer.');
            }

            $this->fireStartAnalyzer();

            $this->_cyclomaticAnalyzer->analyze($packages);

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
     * This method must return an <b>array</b> of class names for required
     * analyzers.
     *
     * @return array(string)
     */
    public function getRequiredAnalyzers()
    {
        return array(
            PHP_Depend_Metrics_CyclomaticComplexity_Analyzer::CLAZZ
        );
    }

    /**
     * Adds a required sub analyzer.
     *
     * @param PHP_Depend_Metrics_AnalyzerI $analyzer The sub analyzer instance.
     *
     * @return void
     */
    public function addAnalyzer(PHP_Depend_Metrics_AnalyzerI $analyzer)
    {
        if ($analyzer instanceof PHP_Depend_Metrics_CyclomaticComplexity_Analyzer) {
            $this->_cyclomaticAnalyzer = $analyzer;
        } else {
            throw new InvalidArgumentException('CC Analyzer required.');
        }
    }

    /**
     * Returns the number of properties the given <b>$node</b>
     * instance.
     *
     * @param PHP_Depend_Code_NodeI $node The context node instance.
     *
     * @return integer
     */
    public function getPropertyCount(PHP_Depend_Code_NodeI $node)
    {
        $metrics = $this->getNodeMetrics($node);
        if (isset($metrics[self::M_PROPERTIES])) {
            return $metrics[self::M_PROPERTIES];
        }
        return 0;
    }

    /**
     * This method will return an <b>array</b> with all generated metric values
     * for the given <b>$node</b>. If there are no metrics for the requested
     * node, this method will return an empty <b>array</b>.
     *
     * @param PHP_Depend_Code_NodeI $node The context node instance.
     *
     * @return array(string=>mixed)
     */
    public function getNodeMetrics(PHP_Depend_Code_NodeI $node)
    {
        $metrics = array();
        if (isset($this->_nodeMetrics[$node->getUUID()])) {
            $metrics = $this->_nodeMetrics[$node->getUUID()];
        }
        return $metrics;
    }

    /**
     * Visits a class node.
     *
     * @param PHP_Depend_Code_Class $class The current class node.
     *
     * @return void
     * @see PHP_Depend_Visitor_AbstractVisitor::visitClass()
     */
    public function visitClass(PHP_Depend_Code_Class $class)
    {
        $this->fireStartClass($class);

        // make sure that all parent classes are analyzed first
        $parentClass = $class->getParentClass();
        if (null !== $parentClass) {
            $parentClass->accept($this);
        }

        // initialize the count of used protected members from the base class
        $this->_baseClassUsageCount[$class->getUUID()] = 0;
        // initialize public method count forthis class
        $this->_publicMethods[$class->getUUID()] = array();
        // initialize count of new services for
        $this->_nons[$class->getUUID()] = 0;

        $this->_nodeMetrics[$class->getUUID()] = array(
            self::M_IMPLEMENTED_INTERFACES       => $class->getInterfaces()->count(),
            self::M_CLASS_INTERFACE_SIZE         => 0,
            self::M_CLASS_SIZE                   => 0,
            self::M_PROPERTIES                   => 0,
            self::M_PROPERTIES_INHERIT           => $this->_calculateVARSi($class),
            self::M_PROPERTIES_NON_PRIVATE       => 0,
            self::M_NUMBER_OF_PROTECTED_MEMBERS  => 0,
            self::M_NUMBER_OF_PUBLIC_METHODS     => 0,
            self::M_WEIGHTED_METHODS             => 0,
            self::M_WEIGHTED_METHODS_INHERIT     => $this->_calculateWMCi($class),
            self::M_WEIGHTED_METHODS_NON_PRIVATE => 0,
            self::M_BASE_CLASS_OVERRIDING_RATIO  => $this->_calculateBOvR($class),
            self::M_BASE_CLASS_USAGE_RATIO       => 0,
            self::M_PNAS                         => $this->_calculatePnas($class)
        );

        foreach ($class->getProperties() as $property) {
            $property->accept($this);
        }


        foreach ($class->getMethods() as $method) {
            $method->accept($this);
        }

        // update base class usage ratio
        $nprotm = null === $parentClass ? 0 : $this->_nodeMetrics[$parentClass->getUUID()][self::M_NUMBER_OF_PROTECTED_MEMBERS];
        $usageRatio = 0 < $nprotm ? $this->_baseClassUsageCount[$class->getUUID()] / $nprotm : 0;
        $this->_nodeMetrics[$class->getUUID()][self::M_BASE_CLASS_USAGE_RATIO] = $usageRatio;

        $this->fireEndClass($class);
    }

    /**
     * Visits a code interface object.
     *
     * @param PHP_Depend_Code_Interface $interface The context code interface.
     *
     * @return void
     * @see PHP_Depend_VisitorI::visitInterface()
     */
    public function visitInterface(PHP_Depend_Code_Interface $interface)
    {
        // Empty visit method, we don't want interface metrics
    }

    /**
     * Visits a method node.
     *
     * @param PHP_Depend_Code_Class $method The method class node.
     *
     * @return void
     * @see PHP_Depend_VisitorI::visitMethod()
     */
    public function visitMethod(PHP_Depend_Code_Method $method)
    {
        $this->fireStartMethod($method);

        // Get parent class uuid
        $uuid = $method->getParent()->getUUID();
        $parentClassUuid = $method->getParent() && $method->getParent()->getParentClass() ? $method->getParent()->getParentClass()->getUUID() : null;

        $ccn = $this->_cyclomaticAnalyzer->getCCN2($method);

        // Increment Weighted Methods Per Class(WMC) value
        $this->_nodeMetrics[$uuid][self::M_WEIGHTED_METHODS] += $ccn;
        // Increment Class Size(CSZ) value
        $this->_nodeMetrics[$uuid][self::M_CLASS_SIZE] += $ccn;

        if ($method->isProtected()) {
            ++$this->_nodeMetrics[$uuid][self::M_NUMBER_OF_PROTECTED_MEMBERS];
        }

        // Increment Non Private values
        if ($method->isPublic()) {
            // Increment Non Private WMC value
            $this->_nodeMetrics[$uuid][self::M_WEIGHTED_METHODS_NON_PRIVATE] += $ccn;
            // Increment Class Interface Size(CIS) value
            $this->_nodeMetrics[$uuid][self::M_CLASS_INTERFACE_SIZE] += $ccn;
            //
            ++$this->_nodeMetrics[$uuid][self::M_NUMBER_OF_PUBLIC_METHODS];
            $this->_publicMethods[$uuid][$method->getName()] = $method->getName();
        }

        $this->fireEndMethod($method);
    }

    /**
     * Visits a property node.
     *
     * @param PHP_Depend_Code_Property $property The property class node.
     *
     * @return void
     * @see PHP_Depend_VisitorI::visitProperty()
     */
    public function visitProperty(PHP_Depend_Code_Property $property)
    {
        $this->fireStartProperty($property);

        // Get parent class uuid
        $uuid = $property->getDeclaringClass()->getUUID();

        // Increment VARS value
        ++$this->_nodeMetrics[$uuid][self::M_PROPERTIES];
        // Increment Class Size(CSZ) value
        ++$this->_nodeMetrics[$uuid][self::M_CLASS_SIZE];

        if ($property->isProtected()) {
            ++$this->_nodeMetrics[$uuid][self::M_NUMBER_OF_PROTECTED_MEMBERS];
        }
        // Increment Non Private values
        if ($property->isPublic()) {
            // Increment Non Private VARS value
            ++$this->_nodeMetrics[$uuid][self::M_PROPERTIES_NON_PRIVATE];
            // Increment Class Interface Size(CIS) value
            ++$this->_nodeMetrics[$uuid][self::M_CLASS_INTERFACE_SIZE];
        }

        $this->fireEndProperty($property);
    }

    /**
     * Calculates the Variables Inheritance of a class metric, this method only
     * counts protected and public properties of parent classes.
     *
     * @param PHP_Depend_Code_Class $class The context class instance.
     *
     * @return integer
     */
    private function _calculateVARSi(PHP_Depend_Code_Class $class)
    {
        // List of properties, this method only counts not overwritten properties
        $properties = array();
        // Collect all properties of the context class
        foreach ($class->getProperties() as $prop) {
            $properties[$prop->getName()] = true;
        }

        // Get parent class and collect all non private properties
        $parent = $class->getParentClass();

        while ($parent !== null) {
            // Get all parent properties
            foreach ($parent->getProperties() as $prop) {
                if (!$prop->isPrivate() && !isset($properties[$prop->getName()])) {
                    $properties[$prop->getName()] = true;
                }
            }
            // Get next parent
            $parent = $parent->getParentClass();
        }
        return count($properties);
    }

    /**
     * Calculates the Weight Method Per Class metric, this method only counts
     * protected and public methods of parent classes.
     *
     * @param PHP_Depend_Code_Class $class The context class instance.
     *
     * @return integer
     */
    private function _calculateWMCi(PHP_Depend_Code_Class $class)
    {
        // List of methods, this method only counts not overwritten methods.
        $ccn = array();

        // First collect all methods of the context class
        foreach ($class->getMethods() as $m) {
            $ccn[$m->getName()] = $this->_cyclomaticAnalyzer->getCCN2($m);
        }

        // Get parent class and collect all non private methods.
        $parent = $class->getParentClass();

        while ($parent !== null) {
            // Count all methods
            foreach ($parent->getMethods() as $m) {
                if (!$m->isPrivate() && !isset($methods[$m->getName()])) {
                    $ccn[$m->getName()] = $this->_cyclomaticAnalyzer->getCCN2($m);
                }
            }
            // Fetch parent class
            $parent = $parent->getParentClass();
        }
        return array_sum($ccn);
    }

    /**
     * Calculates the Base Class Overriding Ratio metric, which is the number of
     * methods that override methods of the parent class devided by the total number of
     * methods in the calss
     * [0..1]
     *
     * @param PHP_Depend_Code_Class $class The context class instance.
     *
     * @return integer
     */
    private function _calculateBOvR(PHP_Depend_Code_Class $class)
    {
        $classMethods = array();
        foreach ($class->getMethods() as $m) {
            if (!$m->isAbstract() && !$m->isStatic() && '__construct' !== $m->getName() && $class->getName() !== $m->getName()) {
                $classMethods[$m->getName()] = $m->getName();
            }
        }

        if (0 < count($classMethods)) {
            $parent = $class->getParentClass();
            if (null !== $parent)
			      {
    		        $numOfBaseClassMethods = 0;
    		        $numOfOverriddenMethods = 0;
                foreach ($parent->getMethods() as $m)
				        {
					          if(!$m->isAbstract() && !$m->isStatic() && '__construct' !== $m->getName() && $parent->getName() !== $m->getName())
					          {
                        ++$numOfBaseClassMethods;
						            if (isset($classMethods[$m->getName()]))
						            {
  					                ++$numOfOverriddenMethods;
						            }
                    }
                }

                if (0 < $numOfBaseClassMethods && 0 < $numOfOverriddenMethods) {
                    return $numOfOverriddenMethods / $numOfBaseClassMethods;
                }
            }
        }

        return 0;
    }

    private function _calculatePnas(PHP_Depend_Code_Class $class)
    {
        $parent = $class->getParentClass();
        if (null !== $parent) {
            $parentClassMethods = array();
            foreach ($parent->getMethods() as $m) {
                if(!$m->isStatic() && '__construct' !== $m->getName() && $parent->getName() !== $m->getName()) {
                    $parentClassMethods[$m->getName()] = $m->getName();
                }
            }
        }

        $numServices = count($parentClassMethods);
        $numNewServices = 0;
        foreach ($class->getMethods() as $m) {
            if (!$m->isStatic() && '__construct' !== $m->getName() && $class->getName() !== $m->getName()) {
                if (!isset($parentClassMethods[$m->getName()]))
                {
                    ++$numServices;
                    ++$numNewServices;
                }
            }
        }

        return 0 < $numServices ? $numNewServices / $numServices : 0;
    }

    /**
     * Counts the used and usable members of the parent class for the given method
     *
     * @param PHP_Depend_Code_Class $class The context class instance.
     *
     * @return integer
     */
    private function _countBaseClassUsage(PHP_Depend_Code_Method $method)
    {
        // get declaring class
        $class = $method->getParent();

        // do not measure interfaces
        if($class instanceof PHP_Depend_Code_Class) {
            $parentClass = $class->getParentClass();

            if(null !== $parentClass) {
                // collect all accessed properties an methods
                $accessedProperties = array();
                $accessedMethods = array();
                foreach ($method->findChildrenOfType(PHP_Depend_Code_ASTSelfReference::CLAZZ) as $reference) {
                    $varParent = $reference->getParent();
                    if(!is_null($varParent)) {
                        foreach($varParent->findChildrenOfType(PHP_Depend_Code_ASTPropertyPostfix::CLAZZ) as $directAccessor) {
                           $accessedProperties[$directAccessor->getImage()] = $directAccessor->getImage();
                        }
                        foreach($varParent->findChildrenOfType(PHP_Depend_Code_ASTMethodPostfix::CLAZZ) as $directAccessor) {
                            $accessedMethods[$directAccessor->getImage()] = $directAccessor->getImage();
                        }
                    }
                }

                // Count all methods
                foreach ($parentClass->getMethods() as $m) {
                    if($m->isProtectd()) {
                        if(isset($accessedMethods[$m->getName()])) {
                            ++$this->_baseClassUsageCount[$class->getUUID()];
                        }
                    }
                }
                foreach ($parentClass->getProperties() as $p) {
                    if($p->isProtectd()) {
                        if(isset($accessedProperties[$p->getName()])) {
                             ++$this->_baseClassUsageCount[$class->getUUID()];
                        }
                    }
                }
            }
        }
    }
}
