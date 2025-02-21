<?php

/**
 * @group PIIReview
 * @covers \PIIReviewHooks
 */
class PIIReviewTest extends MediaWikiIntegrationTestCase {

    /**
     * @covers \PIIReviewHooks::onBeforePageDisplay
     */
    public function testOnBeforePageDisplay() {
        // Create mock objects
        $outputPage = $this->getMockBuilder( OutputPage::class )
            ->disableOriginalConstructor()
            ->getMock();

        $skin = $this->getMockBuilder( Skin::class )
            ->disableOriginalConstructor()
            ->getMock();

        // Create a test configuration
        $config = new HashConfig( [
            'PIIReviewKioskMode' => true
        ] );

        // Set up expectations for the OutputPage mock
        $outputPage->expects( $this->once() )
            ->method( 'getConfig' )
            ->willReturn( $config );

        $outputPage->expects( $this->once() )
            ->method( 'addModules' )
            ->with( 'ext.PIIReview' );

        // Call the hook and check the result
        $result = PIIReviewHooks::onBeforePageDisplay( $outputPage, $skin );
        $this->assertTrue( $result );
    }

    /**
     * @covers \SpecialPIIReview::__construct
     */
    public function testSpecialPageConstruction() {
        $specialPage = new SpecialPIIReview();
        $this->assertInstanceOf( SpecialPIIReview::class, $specialPage );
        $this->assertEquals( 'PIIReview', $specialPage->getName() );
    }

    /**
     * Test the structure of the extension.json file
     */
    public function testExtensionJson() {
        $extensionJsonPath = dirname( __DIR__, 2 ) . '/extension.json';
        $this->assertFileExists( $extensionJsonPath );

        $extensionJson = json_decode( file_get_contents( $extensionJsonPath ), true );
        $this->assertIsArray( $extensionJson );

        // Check mandatory fields
        $this->assertArrayHasKey( 'name', $extensionJson );
        $this->assertEquals( 'PIIReview', $extensionJson['name'] );

        $this->assertArrayHasKey( 'type', $extensionJson );
        $this->assertArrayHasKey( 'license-name', $extensionJson );
        $this->assertArrayHasKey( 'requires', $extensionJson );
        $this->assertArrayHasKey( 'AutoloadClasses', $extensionJson );
        $this->assertArrayHasKey( 'SpecialPages', $extensionJson );
        $this->assertArrayHasKey( 'MessagesDirs', $extensionJson );
    }

    /**
     * Mock test for file scanning function
     * @covers \SpecialPIIReview::scanWatchFolder
     */
    public function testScanWatchFolder() {
        // Instead of mocking, we'll use a different technique for private methods

        // Create a temporary test directory that will actually work
        $tempDir = sys_get_temp_dir() . '/pii-test-' . mt_rand();
        mkdir($tempDir);

        try {
            // Create a test file with a valid image signature
            $testImageFile = $tempDir . '/test.jpg';
            // This is a minimal valid JPEG header
            $jpegData = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xDB";
            file_put_contents($testImageFile, $jpegData);

            // Verify the test file exists
            $this->assertFileExists($testImageFile, "Test file creation failed");

            // Get direct access to the private method using reflection
            $specialPage = new SpecialPIIReview();
            $reflectionMethod = new ReflectionMethod(SpecialPIIReview::class, 'scanWatchFolder');
            $reflectionMethod->setAccessible(true);

            // Call the private method
            $result = $reflectionMethod->invoke($specialPage, $tempDir);

            // First, let's check what mime_content_type thinks of our file
            if (function_exists('mime_content_type')) {
                $mimeType = mime_content_type($testImageFile);
                $this->assertMatchesRegularExpression('/^image\//', $mimeType,
                    "Test setup issue: mime_content_type didn't recognize our test file as an image ($mimeType)");
            }

            // Now test the method result
            $this->assertIsArray($result, "scanWatchFolder should return an array");

            // If the mime type detection worked, we should have found the file
            if (function_exists('mime_content_type')) {
                $this->assertNotEmpty($result, "scanWatchFolder should have found our test image");
                $this->assertEquals('test.jpg', $result[0]['name'], "Found file has incorrect name");
            }

        } finally {
            // Clean up
            if (file_exists($testImageFile)) {
                unlink($testImageFile);
            }
            if (file_exists($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    /**
     * Test that the i18n files exist and contain expected keys
     */
    public function testI18nFiles() {
        $i18nDir = dirname( __DIR__, 2 ) . '/i18n';
        $this->assertDirectoryExists( $i18nDir );

        // Test English messages file
        $enJsonPath = $i18nDir . '/en.json';
        $this->assertFileExists( $enJsonPath );

        $enJson = json_decode( file_get_contents( $enJsonPath ), true );
        $this->assertArrayHasKey( 'piireview', $enJson );
        $this->assertArrayHasKey( 'piireview-desc', $enJson );
        $this->assertArrayHasKey( 'piireview-approve', $enJson );
        $this->assertArrayHasKey( 'piireview-reject', $enJson );
    }
}
