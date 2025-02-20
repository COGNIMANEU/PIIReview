<?php

class SpecialPIIReview extends SpecialPage {
    public function __construct() {
        parent::__construct( 'PIIReview' );
    }

    public function execute( $sub ) {
        $out = $this->getOutput();
        $out->setPageTitle( $this->msg( 'piireview-title' ) );
        
        if ( !$this->getUser()->isAllowed( 'piireview' ) ) {
            throw new PermissionsError( 'piireview' );
        }

        $out->addModules( 'ext.PIIReview' );
        
        // Scan the watch folder for new files
        $watchFolder = $this->getConfig()->get( 'PIIReviewWatchFolder' );
        $files = $this->scanWatchFolder( $watchFolder );
        
        // Display the review interface
        $this->displayReviewInterface( $files );
    }

    private function scanWatchFolder( $folder ) {
        $files = [];
        if ( is_dir( $folder ) ) {
            $iterator = new DirectoryIterator( $folder );
            foreach ( $iterator as $fileInfo ) {
                if ( $fileInfo->isFile() ) {
                    $mime = mime_content_type( $fileInfo->getPathname() );
                    if ( strpos( $mime, 'image/' ) === 0 || strpos( $mime, 'video/' ) === 0 ) {
                        $files[] = [
                            'path' => $fileInfo->getPathname(),
                            'name' => $fileInfo->getFilename(),
                            'type' => $mime
                        ];
                    }
                }
            }
        }
        return $files;
    }

    private function displayReviewInterface( $files ) {
        $out = $this->getOutput();
        
        $html = Html::element( 'div', 
            [ 'id' => 'piireview-container' ],
            $this->msg( 'piireview-loading' )->text()
        );
        
        $out->addHTML( $html );
        
        // Pass the files list to JavaScript
        $out->addJsConfigVars( 'wgPIIReviewFiles', $files );
    }
}
