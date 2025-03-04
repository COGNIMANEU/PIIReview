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

        // Process form submissions
        $this->handleFormSubmissions();

        // Add batch controls and progress tracking
        $out->addHTML('
        <div class="piireview-container">
            <div class="piireview-header">
                <h2>' . $this->msg('piireview-title')->text() . '</h2>
                <div class="piireview-batch-controls">
                    <div class="piireview-progress">
                        <div class="piireview-progress-label">' . $this->msg('piireview-progress')->text() . '</div>
                        <div class="piireview-progress-container">
                            <div class="piireview-progress-bar" style="width: 0%"></div>
                        </div>
                        <div class="piireview-progress-text">0 / 0</div>
                    </div>
                    <div class="piireview-batch-actions">
                        <button class="piireview-batch-approve">' . $this->msg('piireview-batch-approve')->text() . '</button>
                        <button class="piireview-batch-process">' . $this->msg('piireview-batch-process')->text() . '</button>
                    </div>
                </div>
            </div>
            <div class="piireview-filters">
                <div class="piireview-search">
                    <input type="text" placeholder="' . $this->msg('piireview-search-placeholder')->text() . '" class="piireview-search-input">
                </div>
                <div class="piireview-sort">
                    <select class="piireview-sort-select">
                        <option value="name">' . $this->msg('piireview-sort-name')->text() . '</option>
                        <option value="date">' . $this->msg('piireview-sort-date')->text() . '</option>
                        <option value="size">' . $this->msg('piireview-sort-size')->text() . '</option>
                    </select>
                </div>
                <div class="piireview-filter">
                    <select class="piireview-filter-select">
                        <option value="all">' . $this->msg('piireview-filter-all')->text() . '</option>
                        <option value="pii">' . $this->msg('piireview-filter-pii')->text() . '</option>
                        <option value="clear">' . $this->msg('piireview-filter-clear')->text() . '</option>
                    </select>
                </div>
            </div>
            <div class="piireview-content">
    ');

        // Scan the watch folder for new files
        $watchFolder = $this->getConfig()->get('PIIReviewWatchFolder');
        $files = $this->scanWatchFolder($watchFolder);

        if (empty($files)) {
            $out->addHTML('<div class="piireview-empty">' . $this->msg('piireview-no-files')->text() . '</div>');
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

    private function handleFormSubmissions() {
        $request = $this->getRequest();

        if ($request->wasPosted()) {
            $action = $request->getText('action');
            $filePath = $request->getText('file');

            if (!empty($filePath) && file_exists($filePath)) {
                if ($action === 'approve') {
                    // Process approved file
                    $this->approveFile($filePath);
                } elseif ($action === 'reject') {
                    // Process rejected file
                    $this->rejectFile($filePath);
                }
            }
        }
    }

    private function approveFile($filePath) {
        // Get target directory for approved files
        $targetDir = $this->getConfig()->get('PIIReviewApprovedFolder', '/tmp/approved');

        // Create target directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Move file to approved directory
        $fileName = basename($filePath);
        $targetPath = $targetDir . '/' . $fileName;

        if (rename($filePath, $targetPath)) {
            // Log success
            wfDebugLog('PIIReview', "Approved file: $filePath -> $targetPath");

            // Show success message
            $this->getOutput()->addHTML(
                '<div class="successbox">' .
                $this->msg('piireview-approve-success', $fileName)->escaped() .
                '</div>'
            );
        } else {
            // Log error
            wfDebugLog('PIIReview', "Failed to approve file: $filePath");

            // Show error message
            $this->getOutput()->addHTML(
                '<div class="errorbox">' .
                $this->msg('piireview-approve-error', $fileName)->escaped() .
                '</div>'
            );
        }
    }

    private function rejectFile($filePath) {
        // Get target directory for rejected files
        $targetDir = $this->getConfig()->get('PIIReviewRejectedFolder', '/tmp/rejected');

        // Create target directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Move file to rejected directory
        $fileName = basename($filePath);
        $targetPath = $targetDir . '/' . $fileName;

        if (rename($filePath, $targetPath)) {
            // Log success
            wfDebugLog('PIIReview', "Rejected file: $filePath -> $targetPath");

            // Show success message
            $this->getOutput()->addHTML(
                '<div class="successbox">' .
                $this->msg('piireview-reject-success', $fileName)->escaped() .
                '</div>'
            );
        } else {
            // Log error
            wfDebugLog('PIIReview', "Failed to reject file: $filePath");

            // Show error message
            $this->getOutput()->addHTML(
                '<div class="errorbox">' .
                $this->msg('piireview-reject-error', $fileName)->escaped() .
                '</div>'
            );
        }
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
        $fileId = md5($file['path']); // Generate unique ID for the file

        $html = '
    <div class="piireview-card" id="card-' . $fileId . '">
        <div class="piireview-card-header">
            <h3>' . htmlspecialchars($file['name']) . '</h3>
            <span class="piireview-metadata">
                <span class="piireview-filetype">' . htmlspecialchars($file['type']) . '</span>
                <span class="piireview-filesize">' . $this->formatFileSize($file['size']) . '</span>
                <span class="piireview-date">' . $this->getLanguage()->timeanddate(wfTimestamp(TS_MW, $file['modified']), true) . '</span>
            </span>
        </div>
        <div class="piireview-card-content">';

        if (strpos($file['type'], 'image/') === 0) {
            // Add image preview with zoom capability
            $html .= '<div class="piireview-image-container">
                    <img src="data:' . $file['type'] . ';base64,' .
                base64_encode(file_get_contents($file['path'])) .
                '" alt="Preview" class="piireview-image" data-fullsize="data:' . $file['type'] . ';base64,' .
                base64_encode(file_get_contents($file['path'])) . '">
                    <div class="piireview-image-controls">
                        <button class="piireview-zoom-in" title="' . $this->msg('piireview-zoom-in')->text() . '">+</button>
                        <button class="piireview-zoom-out" title="' . $this->msg('piireview-zoom-out')->text() . '">-</button>
                        <button class="piireview-zoom-reset" title="' . $this->msg('piireview-zoom-reset')->text() . '">↺</button>
                    </div>
                  </div>';
        } else if (strpos($file['type'], 'video/') === 0) {
            $html .= '<video controls class="piireview-video">
                    <source src="data:' . $file['type'] . ';base64,' .
                base64_encode(file_get_contents($file['path'])) .
                '" type="' . $file['type'] . '">
                    Your browser does not support video playback.
                  </video>';
        }

        // Add PII detection status indicator
        $html .= '<div class="piireview-pii-status">
                <span class="piireview-status-indicator piireview-status-scanning">
                    ' . $this->msg('piireview-scanning')->text() . '
                </span>
              </div>';

        $html .= '
        </div>
        <div class="piireview-card-actions">
            <form method="post" class="piireview-controls">
                <input type="hidden" name="file" value="' . htmlspecialchars($file['path']) . '">
                <input type="hidden" name="file_id" value="' . $fileId . '">

                <div class="piireview-action-buttons">
                    <button type="submit" name="action" value="approve" class="piireview-button-approve">
                        ' . $this->msg('piireview-approve')->text() . '
                    </button>
                    <button type="submit" name="action" value="reject" class="piireview-button-reject">
                        ' . $this->msg('piireview-reject')->text() . '
                    </button>
                    <button type="button" class="piireview-button-process" data-file-id="' . $fileId . '">
                        ' . $this->msg('piireview-process-pii')->text() . '
                    </button>
                </div>

                <div class="piireview-notes">
                    <textarea name="review_notes" placeholder="' . $this->msg('piireview-notes-placeholder')->text() . '"></textarea>
                </div>
            </form>
        </div>
    </div>';

        $this->getOutput()->addHTML($html);
    }

// Helper method to format file size
    private function formatFileSize($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
