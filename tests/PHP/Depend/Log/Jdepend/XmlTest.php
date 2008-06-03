<?php
/**
 * This file is part of PHP_Depend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008, Manuel Pichler <mapi@pmanuel-pichler.de>.
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
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once dirname(__FILE__) . '/../../AbstractTest.php';
require_once dirname(__FILE__) . '/../DummyAnalyzer.php';

require_once 'PHP/Depend/Parser.php';
require_once 'PHP/Depend/Code/DefaultBuilder.php';
require_once 'PHP/Depend/Code/Tokenizer/InternalTokenizer.php';
require_once 'PHP/Depend/Code/NodeIterator/DefaultPackageFilter.php';
require_once 'PHP/Depend/Log/Jdepend/Xml.php';
require_once 'PHP/Depend/Metrics/Dependency/Analyzer.php';
require_once 'PHP/Depend/Util/FileExtensionFilter.php';
require_once 'PHP/Depend/Util/FileFilterIterator.php';

/**
 * Test case for the jdepend xml logger.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Log
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_Log_Jdepend_XmlTest extends PHP_Depend_AbstractTest
{
    /**
     * Test code structure.
     *
     * @type PHP_Depend_Code_NodeIterator
     * @var PHP_Depend_Code_NodeIterator $packages
     */
    protected $packages = null;
    
    /**
     * Test dependency analyzer.
     *
     * @type PHP_Depend_Metrics_Dependency_Analyzer
     * @var PHP_Depend_Metrics_Dependency_Analyzer $analyzer
     */
    protected $analyzer = null;
    
    /**
     * The temporary file name for the logger result.
     *
     * @type string
     * @var string $resultFile
     */
    protected $resultFile = null;
    
    /**
     * Creates the package structure from a test source file.
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();
        
        $source = dirname(__FILE__) . '/../../_code/code-5.2.x';
        $files  = new PHP_Depend_Util_FileFilterIterator(
            new DirectoryIterator($source),
            new PHP_Depend_Util_FileExtensionFilter(array('php'))
        );
        
        $builder = new PHP_Depend_Code_DefaultBuilder();
        
        foreach ($files as $file) {
            $path = $file->getRealPath();
            $tokz = new PHP_Depend_Code_Tokenizer_InternalTokenizer($path);
            
            $parser = new PHP_Depend_Parser($tokz, $builder);
            $parser->parse();
        }
        
        $this->packages = $builder->getPackages();
        
        $this->analyzer = new PHP_Depend_Metrics_Dependency_Analyzer();
        $this->analyzer->analyze($this->packages);
        
        $filter = new PHP_Depend_Code_NodeIterator_DefaultPackageFilter();
        $this->packages->addFilter($filter);
        
        $this->resultFile = tempnam(sys_get_temp_dir(), 'pdepend-log.xml');
    }
    
    /**
     * Removes the temporary log files.
     *
     * @return void
     */
    protected function tearDown()
    {
        @unlink($this->resultFile);
        
        parent::tearDown();
    }
    
    /**
     * Tests that {@link PHP_Depend_Log_Summary_Xml::write()} generates the 
     * expected document structure for the source, but without any applied 
     * metrics.
     *
     * @return void
     */
    public function testXmlLogWithoutMetrics()
    {
        $log = new PHP_Depend_Log_Jdepend_Xml($this->resultFile);
        $log->log($this->analyzer);
        $log->setCode($this->packages);
        $log->close();
        
        $fileName = 'pdepend-log.xml';
        $this->assertXmlStringEqualsXmlString(
            $this->getNormalizedPathXml(dirname(__FILE__) . "/_expected/{$fileName}"),
            file_get_contents($this->resultFile)
        );
    }
    
    public function testXmlLogAcceptsOnlyTheCorrectAnalyzer()
    {
        $logger = new PHP_Depend_Log_Jdepend_Xml($this->resultFile);
        
        $this->assertFalse($logger->log(new PHP_Depend_Log_DummyAnalyzer()));
        $this->assertTrue($logger->log(new PHP_Depend_Metrics_Dependency_Analyzer()));
    }
    
    /**
     * Normalizes the file references within the expected result document.
     *
     * @param string $fileName File name of the expected result document.
     * 
     * @return string The prepared xml document
     */
    protected function getNormalizedPathXml($fileName)
    {
        $expected                     = new DOMDocument('1.0', 'UTF-8');
        $expected->preserveWhiteSpace = false;
        $expected->load($fileName);
        
        $xpath = new DOMXPath($expected);
        $result = $xpath->query('//Class[@sourceFile]');
        
        $path = realpath(dirname(__FILE__) . '/../../_code/code-5.2.x') . '/';
        
        // Adjust file path
        foreach ($result as $class) {
            $sourceFile = $class->getAttribute('sourceFile');
            $sourceFile = $path . basename($sourceFile);
            
            $class->setAttribute('sourceFile', $sourceFile);
        }
        
        return $expected->saveXML();
    }
    
}