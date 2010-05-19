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

require_once dirname(__FILE__) . '/../AbstractTest.php';

require_once 'PHP/Depend/Metrics/AbstractAnalyzer.php';
require_once 'PHP/Depend/Metrics/AnalyzerI.php';

require_once 'PHP/Depend/Code/ASTSelfReference.php';
require_once 'PHP/Depend/Metrics/ClassLevel/Analyzer.php';
require_once 'PHP/Depend/Metrics/NodeCount/Analyzer.php';
require_once 'PHP/Depend/Metrics/Cohesion/Analyzer.php';

/**
 * Test case for the cohesion analyzer.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Metrics
 * @author     Jan Schumann <js@schumann-it.com>
 * @copyright  2008-2010 Jan Schumann. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://pdepend.org/
 */
class PHP_Depend_Metrics_Cohesion_AnalyzerTest extends PHP_Depend_Metrics_AbstractTest
{
    /**
     * Tests that the {@link PHP_Depend_Metrics_Cohesion_Analyzer::analyze()}
     * method fails with an exception if no class level analyzer was set.
     *
     * @return void
     */
    public function testAnalyzerFailsWithoutClassLevelAnalyzerFail()
    {
        $package  = new PHP_Depend_Code_Package('package1');
        $packages = new PHP_Depend_Code_NodeIterator(array($package));

        $nodeCountAnalyzer = new PHP_Depend_Metrics_NodeCount_Analyzer();

        $analyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();
        $analyzer->addAnalyzer($nodeCountAnalyzer);

        $this->setExpectedException('RuntimeException', 'Missing required class level analyzer.');

        $analyzer->analyze($packages);
    }

    /**
     * Tests that the {@link PHP_Depend_Metrics_Cohesion_Analyzer::analyze()}
     * method fails with an exception if no node count analyzer was set.
     *
     * @return void
     */
    public function testAnalyzerFailsWithoutNodeCountAnalyzerFail()
    {
        $package  = new PHP_Depend_Code_Package('package1');
        $packages = new PHP_Depend_Code_NodeIterator(array($package));

        $classLevelAnalyzer = new PHP_Depend_Metrics_ClassLevel_Analyzer();

        $analyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();
        $analyzer->addAnalyzer($classLevelAnalyzer);

        $this->setExpectedException('RuntimeException', 'Missing required node count analyzer.');

        $analyzer->analyze($packages);
    }

    /**
     * Tests that the {@link PHP_Depend_Metrics_Cohesion_Analyzer::analyze()}
     * method fails with an exception if other than node count or class level analyzer is set.
     *
     * @return void
     */
    public function testAnalyzerFailsWithOtherThanNodeCountOrClassLevelAnalyzerFail()
    {
        $package  = new PHP_Depend_Code_Package('package1');
        $packages = new PHP_Depend_Code_NodeIterator(array($package));

        // use same class as tested, because i know this will be available :-)
        $cohesionAnalyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();

        $analyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();
        $this->setExpectedException('InvalidArgumentException', 'ClassLevel and NodeCount Analyzers required.');

        $analyzer->addAnalyzer($cohesionAnalyzer);
    }

    /**
     * Tests that the analyzer calculates correct lcom and tcc metrics for classes
     * having all methods connected with one property
     *
     * Some calculations have prerequisites, thus all test-classes have 3 methods and two properties
     * to meet all of them :-)
     *
     * @return void
     */
    public function testAnalyzerCalculatesCorrectMetricsWithAllMethodsConnectedByOneProperty()
    {
        $packages = self::parseTestCaseSource(__METHOD__);
        $package  = $packages->current();

        $classLevelAnalyzer = new PHP_Depend_Metrics_ClassLevel_Analyzer();
        $classLevelAnalyzer->addAnalyzer(new PHP_Depend_Metrics_CyclomaticComplexity_Analyzer());
        $nodeCountAnalyzer = new PHP_Depend_Metrics_NodeCount_Analyzer();

        $analyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();
        $analyzer->addAnalyzer($classLevelAnalyzer);
        $analyzer->addAnalyzer($nodeCountAnalyzer);

        $analyzer->analyze($packages);

        $this->assertEquals(1, $package->getClasses()->count());
        $classes = $package->getClasses();

        foreach($classes as $class) {
            $metrics = $analyzer->getNodeMetrics($class);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CK, $metrics);
            $this->assertEquals(0, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CK]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_HS, $metrics);
            $this->assertEquals(0.5, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_HS]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CG, $metrics);
            $this->assertEquals(0.75, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CG]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_TCC, $metrics);
            $this->assertEquals(1, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_TCC]);
        }
    }

    /**
     * Tests that the analyzer calculates correct lcom and tcc metrics for classes
     * having all methods connected with all properties
     *
     * Some calculations have prerequisites, thus all test-classes have 3 methods and two properties
     * to meet all of them :-)
     *
     * @return void
     */
    public function testAnalyzerCalculatesCorrectMetricsWithAllMethodsConnectedByAllProperties()
    {
        $packages = self::parseTestCaseSource(__METHOD__);
        $package  = $packages->current();

        $classLevelAnalyzer = new PHP_Depend_Metrics_ClassLevel_Analyzer();
        $classLevelAnalyzer->addAnalyzer(new PHP_Depend_Metrics_CyclomaticComplexity_Analyzer());
        $nodeCountAnalyzer = new PHP_Depend_Metrics_NodeCount_Analyzer();

        $analyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();
        $analyzer->addAnalyzer($classLevelAnalyzer);
        $analyzer->addAnalyzer($nodeCountAnalyzer);

        $analyzer->analyze($packages);

        $this->assertEquals(1, $package->getClasses()->count());
        $classes = $package->getClasses();

        foreach($classes as $class) {
            $metrics = $analyzer->getNodeMetrics($class);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CK, $metrics);
            $this->assertEquals(0, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CK]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_HS, $metrics);
            $this->assertEquals(0, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_HS]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CG, $metrics);
            $this->assertEquals(0, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CG]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_TCC, $metrics);
            $this->assertEquals(1, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_TCC]);
        }
    }

    /**
     * Tests that the analyzer calculates correct lcom and tcc metrics for classes
     * having all methods connected with all properties
     *
     * Some calculations have prerequisites, thus all test-classes have 3 methods and two properties
     * to meet all of them :-)
     *
     * @return void
     */
    public function testAnalyzerCalculatesCorrectMetricsWithAllButOneMethodConnectedByAllProperties()
    {
        $packages = self::parseTestCaseSource(__METHOD__);
        $package  = $packages->current();

        $classLevelAnalyzer = new PHP_Depend_Metrics_ClassLevel_Analyzer();
        $classLevelAnalyzer->addAnalyzer(new PHP_Depend_Metrics_CyclomaticComplexity_Analyzer());
        $nodeCountAnalyzer = new PHP_Depend_Metrics_NodeCount_Analyzer();

        $analyzer = new PHP_Depend_Metrics_Cohesion_Analyzer();
        $analyzer->addAnalyzer($classLevelAnalyzer);
        $analyzer->addAnalyzer($nodeCountAnalyzer);

        $analyzer->analyze($packages);

        $this->assertEquals(1, $package->getClasses()->count());
        $classes = $package->getClasses();

        foreach($classes as $class) {
            $metrics = $analyzer->getNodeMetrics($class);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CK, $metrics);
            $this->assertEquals(1, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CK]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_HS, $metrics);
            $this->assertEquals('0.333333333333', (string)$metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_HS]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CG, $metrics);
            $this->assertEquals(0.5, $metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_LCOM_CG]);

            $this->assertArrayHasKey(PHP_Depend_Metrics_Cohesion_Analyzer::M_TCC, $metrics);
            $this->assertEquals('0.333333333333', (string)$metrics[PHP_Depend_Metrics_Cohesion_Analyzer::M_TCC]);
        }
    }

}