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

        // Get the current subfolder from the request, if any
        $request = $this->getRequest();
        $subPath = $request->getText('path', '');

        // Check if we're doing a recursive search
        $searchQuery = $request->getText('search', '');
        $isRecursiveSearch = $request->getBool('recursive', false);

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
    ');

        // Scan the watch folder for files and directories
        $watchFolder = $this->getConfig()->get('PIIReviewWatchFolder');

        // If we're doing a recursive search, use the recursive scanner
        if ($isRecursiveSearch && !empty($searchQuery)) {
            $result = $this->scanRecursively($watchFolder, $subPath, $searchQuery);

            // Add search status indicator
            $out->addHTML('
                <div class="piireview-search-status">
                    <span class="piireview-recursive-search-indicator">' .
                $this->msg('piireview-recursive-search')->text() . ' "' .
                htmlspecialchars($searchQuery) . '"</span>
                    <a href="' . $this->getPageTitle()->getLocalURL(['path' => $subPath]) .
                '" class="piireview-clear-search">' .
                $this->msg('piireview-clear-search')->text() . '</a>
                </div>
            ');
        } else {
            $result = $this->scanWatchFolder($watchFolder, $subPath);
        }

        // Add folder navigation (only if not in recursive search)
        if (!$isRecursiveSearch) {
            $this->displayFolderNavigation($watchFolder, $result['currentPath']);
        }

        // Add filters and search
        $out->addHTML('
            <div class="piireview-filters">
                <div class="piireview-search">
                    <input type="text" placeholder="' . $this->msg('piireview-search-placeholder')->text() . '" class="piireview-search-input" value="' . htmlspecialchars($searchQuery) . '">
                    <label class="piireview-recursive-search-label">
                        <input type="checkbox" class="piireview-recursive-search-checkbox" ' . ($isRecursiveSearch ? 'checked' : '') . '>
                        ' . $this->msg('piireview-search-recursive')->text() . '
                    </label>
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

        // Display directories first (but not during recursive search)
        if (!$isRecursiveSearch) {
            foreach ($result['directories'] as $dir) {
                $this->displayDirectoryCard($dir);
            }
        }

        // Then display files
        if (empty($result['files'])) {
            if (empty($result['directories']) || $isRecursiveSearch) {
                $out->addHTML('<div class="piireview-empty">' . $this->msg('piireview-no-files')->text() . '</div>');
            }
        } else {
            foreach ($result['files'] as $file) {
                $this->displayFileCard($file);
            }
        }

        $out->addHTML('
            </div>
        </div>
    ');
    }

    /**
     * Display the folder navigation breadcrumb and path controls
     *
     * @param string $baseFolder The base watch folder
     * @param string $currentPath The current relative path within the watch folder
     */
    private function displayFolderNavigation($baseFolder, $currentPath) {
        $out = $this->getOutput();

        // Build breadcrumb segments
        $pathParts = $currentPath ? explode('/', $currentPath) : [];
        $breadcrumbs = [];

        // Start with root
        $breadcrumbs[] = [
            'name' => $this->msg('piireview-root-folder')->text(),
            'path' => '',
        ];

        // Add intermediate paths
        $currentSegment = '';
        foreach ($pathParts as $part) {
            $currentSegment = $currentSegment ? $currentSegment . '/' . $part : $part;
            $breadcrumbs[] = [
                'name' => $part,
                'path' => $currentSegment,
            ];
        }

        // Build the breadcrumb HTML
        $breadcrumbHtml = '<div class="piireview-breadcrumb">';
        $isFirst = true;

        foreach ($breadcrumbs as $crumb) {
            if (!$isFirst) {
                $breadcrumbHtml .= ' <span class="piireview-breadcrumb-separator">/</span> ';
            }

            $isLast = ($crumb === end($breadcrumbs));

            if ($isLast) {
                $breadcrumbHtml .= '<span class="piireview-breadcrumb-current">' . htmlspecialchars($crumb['name']) . '</span>';
            } else {
                $breadcrumbHtml .= '<a href="' . $this->getPageTitle()->getLocalURL(['path' => $crumb['path']]) .
                    '" class="piireview-breadcrumb-link">' . htmlspecialchars($crumb['name']) . '</a>';
            }

            $isFirst = false;
        }

        $breadcrumbHtml .= '</div>';

        // Display full folder path
        $fullPathHtml = '<div class="piireview-full-path">' .
            $this->msg('piireview-current-folder')->escaped() . ' ' .
            htmlspecialchars($baseFolder . ($currentPath ? '/' . $currentPath : '')) .
            '</div>';

        // Add to output
        $out->addHTML('<div class="piireview-navigation">' . $breadcrumbHtml . $fullPathHtml . '</div>');
    }

    /**
     * Display a directory card for navigation
     *
     * @param array $dir Directory information
     */
    private function displayDirectoryCard($dir) {
        $html = '
    <div class="piireview-directory-card">
        <a href="' . $this->getPageTitle()->getLocalURL(['path' => $dir['path']]) . '" class="piireview-directory-link">
            <div class="piireview-directory-icon"></div>
            <div class="piireview-directory-name">' . htmlspecialchars($dir['name']) . '</div>
            <div class="piireview-directory-info">
                <span class="piireview-directory-date">' .
            $this->getLanguage()->timeanddate(wfTimestamp(TS_MW, $dir['modified']), true) .
            '</span>
            </div>
        </a>
    </div>';

        $this->getOutput()->addHTML($html);
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

    /**
     * Scan the watch folder and return files and directories
     *
     * @param string $folder Base folder to scan
     * @param string $subPath Optional sub-path within base folder
     * @return array Array containing 'files' and 'directories'
     */
    private function scanWatchFolder($folder, $subPath = '') {
        $result = [
            'files' => [],
            'directories' => [],
            'currentPath' => $subPath
        ];

        $fullPath = $subPath ? $folder . '/' . $subPath : $folder;

        if (!is_dir($fullPath)) {
            return $result;
        }

        $iterator = new DirectoryIterator($fullPath);
        foreach ($iterator as $item) {
            // Skip . and .. directories
            if ($item->isDot()) {
                continue;
            }

            $relativePath = $subPath ? $subPath . '/' . $item->getFilename() : $item->getFilename();

            if ($item->isDir()) {
                $result['directories'][] = [
                    'name' => $item->getFilename(),
                    'path' => $relativePath,
                    'modified' => $item->getMTime()
                ];
            } else if ($item->isFile()) {
                $mime = mime_content_type($item->getPathname());
                if (strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0) {
                    $result['files'][] = [
                        'path' => $item->getPathname(),
                        'name' => $item->getFilename(),
                        'relativePath' => $relativePath,
                        'type' => $mime,
                        'size' => $item->getSize(),
                        'modified' => $item->getMTime()
                    ];
                }
            }
        }

        // Sort directories and files by name
        usort($result['directories'], function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        usort($result['files'], function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Scan the watch folder recursively and return files matching the search query
     *
     * @param string $folder Base folder to scan
     * @param string $subPath Optional sub-path within base folder
     * @param string $searchQuery Search query to match against file names
     * @return array Array containing 'files' and 'directories'
     */
    private function scanRecursively($folder, $subPath = '', $searchQuery = '') {
        $result = [
            'files' => [],
            'directories' => [],
            'currentPath' => $subPath
        ];

        if (empty($searchQuery)) {
            return $result;
        }

        $basePath = $subPath ? $folder . '/' . $subPath : $folder;

        // Use RecursiveDirectoryIterator for deep scanning
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            // Only process files, skip directories in results
            if ($item->isFile()) {
                $fileName = $item->getFilename();

                // If the filename contains the search query (case-insensitive)
                if (stripos($fileName, $searchQuery) !== false) {
                    $mime = mime_content_type($item->getPathname());

                    // Only include image and video files
                    if (strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0) {
                        // Get the relative path from the base folder
                        $fileRelativePath = substr($item->getPathname(), strlen($folder) + 1);
                        $parentPath = dirname($fileRelativePath);
                        if ($parentPath === '.') {
                            $parentPath = '';
                        }

                        $result['files'][] = [
                            'path' => $item->getPathname(),
                            'name' => $fileName,
                            'relativePath' => $fileRelativePath,
                            'parentPath' => $parentPath,  // Store parent path for context
                            'type' => $mime,
                            'size' => $item->getSize(),
                            'modified' => $item->getMTime()
                        ];
                    }
                }
            }
        }

        // Sort files by name
        usort($result['files'], function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    private function displayFileCard($file) {
        $fileId = md5($file['path']); // Generate unique ID for the file

        // Check if we need to display the parent folder info (for recursive search)
        $parentFolderInfo = '';
        if (isset($file['parentPath']) && !empty($file['parentPath'])) {
            $parentFolderInfo = '<div class="piireview-parent-path">' .
                $this->msg('piireview-location')->text() . ': ' .
                '<a href="' . $this->getPageTitle()->getLocalURL(['path' => $file['parentPath']]) . '">' .
                htmlspecialchars($file['parentPath']) . '</a></div>';
        }

        $html = '
    <div class="piireview-card" id="card-' . $fileId . '">
        <div class="piireview-card-header">
            <h3>' . htmlspecialchars($file['name']) . '</h3>
            <span class="piireview-metadata">
                <span class="piireview-filetype">' . htmlspecialchars($file['type']) . '</span>
                <span class="piireview-filesize">' . $this->formatFileSize($file['size']) . '</span>
                <span class="piireview-date">' . $this->getLanguage()->timeanddate(wfTimestamp(TS_MW, $file['modified']), true) . '</span>
            </span>
            ' . $parentFolderInfo . '
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
