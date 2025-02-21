<?php

/**
 * @group PIIReview
 */
class SpecialPIIReviewTest extends MediaWikiIntegrationTestCase {

    protected $specialPage;

    protected function setUp(): void {
        parent::setUp();
        $this->specialPage = new SpecialPIIReview();
    }
    /**
     * @covers \SpecialPIIReview
     */
    public function testSpecialPageCreation() {
        $this->assertInstanceOf(SpecialPIIReview::class, $this->specialPage);
    }
    /**
     * @covers \SpecialPIIReview
     */
    public function testExecuteDoesNotThrow() {
        $this->expectNotToPerformAssertions();

        // Create a context with a FauxRequest
        $context = RequestContext::getMain();
        $context->setRequest(new FauxRequest());
        $this->specialPage->setContext($context);

        // This should not throw an exception
        $this->specialPage->execute(null);
    }
    /**
     * @covers \SpecialPIIReview
     */
    // Test scanning functionality with a mock file system
    public function testScanWatchFolder() {
        // Use reflection to access private method
        $method = new ReflectionMethod(SpecialPIIReview::class, 'scanWatchFolder');
        $method->setAccessible(true);

        // Create a mock for the directory using a temp directory
        $tempDir = sys_get_temp_dir() . '/pii-test-' . mt_rand();
        mkdir($tempDir);

        try {
            // Create a test file
            $testFile = $tempDir . '/test.jpg';
            file_put_contents($testFile, 'fake image data');

            // Call the method
            $result = $method->invoke($this->specialPage, $tempDir);

            // Basic validation that it returns an array
            $this->assertIsArray($result);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            if (file_exists($tempDir)) {
                rmdir($tempDir);
            }
        }
    }
}
