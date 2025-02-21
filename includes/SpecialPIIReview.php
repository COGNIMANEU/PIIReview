<?php

class SpecialPIIReview extends SpecialPage {
    public function __construct() {
        parent::__construct('PIIReview');
    }

    public function execute($sub) {
        $out = $this->getOutput();
        $out->setPageTitle($this->msg('piireview-title')->text());

        if (!$this->getUser()->isAllowed('piireview')) {
            throw new PermissionsError('piireview');
        }

        $out->addModules('ext.PIIReview');

        // Add container structure
        $out->addHTML('
            <div class="piireview-container">
                <div class="piireview-header">
                    <h2>' . $this->msg('piireview-title')->text() . '</h2>
                </div>
                <div class="piireview-content">
        ');

        // Scan the watch folder for new files
        $watchFolder = $this->getConfig()->get('PIIReviewWatchFolder');
        $files = $this->scanWatchFolder($watchFolder);

        if (empty($files)) {
            $out->addHTML('<p>' . $this->msg('piireview-no-files')->text() . '</p>');
        } else {
            foreach ($files as $file) {
                $this->displayFileCard($file);
            }
        }

        $out->addHTML('
                </div>
            </div>
        ');
    }

    private function scanWatchFolder($folder) {
        $files = [];
        if (is_dir($folder)) {
            $iterator = new DirectoryIterator($folder);
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $mime = mime_content_type($fileInfo->getPathname());
                    if (strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0) {
                        $files[] = [
                            'path' => $fileInfo->getPathname(),
                            'name' => $fileInfo->getFilename(),
                            'type' => $mime,
                            'size' => $fileInfo->getSize(),
                            'modified' => $fileInfo->getMTime()
                        ];
                    }
                }
            }
        }
        return $files;
    }

    private function displayFileCard($file) {
        $html = '
        <div class="piireview-card">
            <div class="piireview-card-header">
                <h3>' . htmlspecialchars($file['name']) . '</h3>
            </div>
            <div class="piireview-card-content">';

        if (strpos($file['type'], 'image/') === 0) {
            $html .= '<img src="data:' . $file['type'] . ';base64,' .
                    base64_encode(file_get_contents($file['path'])) .
                    '" alt="Preview">';
        } else if (strpos($file['type'], 'video/') === 0) {
            $html .= '<video controls>
                        <source src="data:' . $file['type'] . ';base64,' .
                        base64_encode(file_get_contents($file['path'])) .
                        '" type="' . $file['type'] . '">
                        Your browser does not support video playback.
                    </video>';
        }

        $html .= '
            </div>
            <div class="piireview-card-footer">
                <form method="post" class="piireview-controls">
                    <input type="hidden" name="file" value="' . htmlspecialchars($file['path']) . '">
                    <button type="submit" name="action" value="approve" class="piireview-button-approve">
                        ' . $this->msg('piireview-approve')->text() . '
                    </button>
                    <button type="submit" name="action" value="reject" class="piireview-button-reject">
                        ' . $this->msg('piireview-reject')->text() . '
                    </button>
                </form>
            </div>
        </div>';

        $this->getOutput()->addHTML($html);
    }
}
