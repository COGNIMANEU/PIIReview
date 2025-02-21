<?php

/**
 * @group PIIReview
 */
class PIIReviewHooksTest extends MediaWikiIntegrationTestCase {

    /**
     * @covers \PIIReviewHooks::onBeforePageDisplay
     */
    public function testOnBeforePageDisplay() {
        $outputPage = $this->getMockBuilder(OutputPage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $skin = $this->getMockBuilder(Skin::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = new HashConfig([
            'PIIReviewKioskMode' => true
        ]);

        $outputPage->expects($this->once())
            ->method('getConfig')
            ->willReturn($config);

        $outputPage->expects($this->once())
            ->method('addModules')
            ->with('ext.PIIReview');

        $result = PIIReviewHooks::onBeforePageDisplay($outputPage, $skin);
        $this->assertTrue($result);
    }
}
