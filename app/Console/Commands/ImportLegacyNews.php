<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLegacyNews extends Command
{
    protected $signature = 'import:legacy-news
        {--csv-dir= : Directory containing the CSV export files}
        {--legacy-images= : Directory containing legacy images}
        {--dry-run : Preview changes without inserting}';

    protected $description = 'Import news articles from legacy DNN (DotNetNuke) database CSV export';

    private array $stats = [
        'categories' => 0,
        'users' => 0,
        'articles' => 0,
        'tags' => 0,
        'skipped' => 0,
        'images_copied' => 0,
    ];

    public function handle(): int
    {
        $csvDir = $this->option('csv-dir') ?? 'E:\\yazdrud.ir\\sqlserver';
        $legacyImagesDir = $this->option('legacy-images') ?? 'E:\\yazdrud.ir\\یزد\\Portals\\36';
        $dryRun = $this->option('dry-run');

        if (!is_dir($csvDir)) {
            $this->error("CSV directory not found: {$csvDir}");
            return Command::FAILURE;
        }

        $this->info('=== Import Legacy News from DNN ===');
        if ($dryRun) {
            $this->warn('*** DRY RUN MODE - No data will be inserted ***');
        }

        // 1. Import Categories
        $this->importCategories($csvDir, $dryRun);

        // 2. Import Users (authors)
        $this->importUsers($csvDir, $dryRun);

        // 3. Import Articles
        $this->importArticles($csvDir, $legacyImagesDir, $dryRun);

        // 4. Import Tags
        $this->importTags($csvDir, $dryRun);

        // Summary
        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->table(
            ['Item', 'Count'],
            [
                ['Categories', $this->stats['categories']],
                ['Users', $this->stats['users']],
                ['Articles', $this->stats['articles']],
                ['Articles Skipped', $this->stats['skipped']],
                ['Tags', $this->stats['tags']],
                ['Images Copied', $this->stats['images_copied']],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Parse a CSV file exported by bcp with UTF-8 encoding and || delimiter.
     */
    private function parseCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            $this->warn("File not found: {$filePath}");
            return [];
        }

        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Split by || delimiter
            $fields = explode('||', $line);
            $rows[] = $fields;
        }
        fclose($handle);

        return $rows;
    }

    private function importCategories(string $csvDir, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Importing Categories ---');

        $rows = $this->parseCsv($csvDir . '/categories.csv');
        if (empty($rows)) {
            $this->warn('No categories found.');
            return;
        }

        // Expected columns: CategoryID, ModuleID, Name, Description, ParentID, SortOrder
        foreach ($rows as $row) {
            if (count($row) < 6) {
                continue;
            }

            $legacyId = (int) $row[0];
            $name = trim($row[2] ?? '');
            $description = trim($row[3] ?? '');
            $sortOrder = (int) ($row[5] ?? 0);

            if (empty($name)) {
                continue;
            }

            $slug = Str::slug($name);

            if (!$dryRun) {
                NewsCategory::updateOrCreate(
                    ['name' => $name],
                    [
                        'slug' => $slug,
                        'description' => $description,
                        'is_active' => true,
                        'ordering' => $sortOrder,
                    ]
                );
            }

            $this->stats['categories']++;
            $this->line("  [{$legacyId}] {$name}");
        }
    }

    private function importUsers(string $csvDir, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Importing Users (Authors) ---');

        $rows = $this->parseCsv($csvDir . '/users.csv');
        if (empty($rows)) {
            $this->warn('No users found.');
            return;
        }

        // Expected columns: UserID, Username, DisplayName, Email, FirstName, LastName
        foreach ($rows as $row) {
            if (count($row) < 4) {
                continue;
            }

            $legacyId = (int) $row[0];
            $username = trim($row[1] ?? '');
            $displayName = trim($row[2] ?? $username);
            $email = trim($row[3] ?? '');

            if (empty($username)) {
                continue;
            }

            // Check if user already exists by username
            $existingUser = User::where('username', $username)->first();
            if ($existingUser) {
                $this->line("  [{$legacyId}] {$username} - already exists (ID: {$existingUser->id})");
                continue;
            }

            if (!$dryRun) {
                User::create([
                    'username' => $username,
                    'name' => $displayName,
                    'email' => !empty($email) ? $email : "{$username}@legacy.local",
                    'password' => bcrypt(Str::random(32)),
                    'is_active' => true,
                ]);
            }

            $this->stats['users']++;
            $this->line("  [{$legacyId}] {$username} ({$displayName})");
        }
    }

    private function importArticles(string $csvDir, string $legacyImagesDir, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Importing Articles ---');

        $articles = $this->parseCsv($csvDir . '/articles.csv');
        $pages = $this->parseCsv($csvDir . '/article_pages.csv');
        $articleCategories = $this->parseCsv($csvDir . '/article_categories.csv');
        $images = $this->parseCsv($csvDir . '/images.csv');

        if (empty($articles)) {
            $this->warn('No articles found.');
            return;
        }

        // Build lookup: article_id => page text (body)
        // article_pages.csv columns: PageID, ArticleID, PageTitle, PageText [, SortOrder]
        // NOTE: The CSV has two quirks:
        //   1. SortOrder is often missing (4 fields instead of 5)
        //   2. PageText (HTML body) contains embedded newlines, splitting records across rows
        //   We handle this by accumulating continuation rows (1-2 fields) into the preceding record.
        $pageLookup = [];
        $pendingPage = null; // ['articleId' => int, 'sortOrder' => int, 'body' => string]
        foreach ($pages as $page) {
            $fieldCount = count($page);
            if ($fieldCount >= 4) {
                // Flush pending record
                if ($pendingPage !== null) {
                    $aid = $pendingPage['articleId'];
                    if (!isset($pageLookup[$aid]) || $pendingPage['sortOrder'] === 0) {
                        $pageLookup[$aid] = trim($pendingPage['body']);
                    }
                }
                // Start new record
                $articleId = (int) $page[1];
                $sortOrder = isset($page[4]) ? (int) $page[4] : 0;
                $pageText = $page[3] ?? '';
                $pendingPage = [
                    'articleId' => $articleId,
                    'sortOrder' => $sortOrder,
                    'body' => $pageText,
                ];
            } elseif ($fieldCount >= 1 && $pendingPage !== null) {
                // Continuation of previous record's body text (body had embedded newlines)
                $pendingPage['body'] .= "\n" . $page[0];
            }
        }
        // Flush last pending record
        if ($pendingPage !== null) {
            $aid = $pendingPage['articleId'];
            if (!isset($pageLookup[$aid]) || $pendingPage['sortOrder'] === 0) {
                $pageLookup[$aid] = trim($pendingPage['body']);
            }
        }

        // Build lookup: article_id => category_ids
        $categoryLookup = [];
        foreach ($articleCategories as $ac) {
            if (count($ac) >= 2) {
                $articleId = (int) $ac[0];
                $categoryId = (int) $ac[1];
                if (!isset($categoryLookup[$articleId])) {
                    $categoryLookup[$articleId] = [];
                }
                $categoryLookup[$articleId][] = $categoryId;
            }
        }

        // Build lookup: article_id => images
        $imageLookup = [];
        foreach ($images as $img) {
            if (count($img) >= 5) {
                $articleId = (int) $img[1];
                if (!isset($imageLookup[$articleId])) {
                    $imageLookup[$articleId] = [];
                }
                $imageLookup[$articleId][] = [
                    'fileName' => $img[2] ?? '',
                    'extension' => $img[3] ?? '',
                    'folder' => $img[4] ?? '',
                ];
            }
        }

        // Expected article columns:
        // ArticleID, Title, Summary, CreatedDate, LastUpdate, IsApproved, IsDraft, IsFeatured,
        // NumberOfViews, CommentCount, AuthorID, ModuleID, ImageUrl, URL, StartDate, EndDate, Rating, RatingCount
        $legacyImagesStorage = storage_path('app/public/media/legacy');

        // Pre-scan legacy images
        $legacyImageFiles = [];
        if (is_dir($legacyImagesStorage)) {
            $files = scandir($legacyImagesStorage);
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..') {
                    $legacyImageFiles[] = $f;
                }
            }
        }

        // Reconstruct articles that were split across CSV lines due to
        // embedded newlines in the Summary (body HTML) field.
        // Pattern: 3-field rows [ID, Title, Summary_start] followed by
        // 16-field continuation rows [Summary_rest, CreatedDate, ..., RatingCount].
        $reconstructed = [];
        $pendingRow = null;
        foreach ($articles as $row) {
            $fc = count($row);
            // Row starting with numeric ID = start of a new article record
            if ($fc >= 3 && is_numeric(trim($row[0]))) {
                if ($pendingRow !== null) {
                    $reconstructed[] = $pendingRow;
                }
                $pendingRow = $row;
            } elseif ($pendingRow !== null && $fc >= 1) {
                // Continuation row — append field 0 to pending summary,
                // then fill remaining columns from continuation fields 1..N
                $pendingRow[2] = ($pendingRow[2] ?? '') . "\n" . $row[0];
                for ($i = 1; $i < $fc; $i++) {
                    $colIdx = $i + 2; // skip ArticleID(0), Title(1), Summary_part(2 already merged)
                    if ($colIdx < 18) {
                        $pendingRow[$colIdx] = $row[$i];
                    }
                }
            }
        }
        if ($pendingRow !== null) {
            $reconstructed[] = $pendingRow;
        }
        $articles = $reconstructed;

        foreach ($articles as $row) {
            if (count($row) < 10) {
                continue;
            }

            $legacyId = (int) $row[0];
            $title = trim($row[1] ?? '');
            $summary = trim($row[2] ?? '');
            $createdDate = trim($row[3] ?? '');
            $lastUpdate = trim($row[4] ?? '');
            $isApproved = (int) ($row[5] ?? 1);
            $isDraft = (int) ($row[6] ?? 0);
            $isFeatured = (int) ($row[7] ?? 0);
            $viewsCount = (int) ($row[8] ?? 0);
            $commentCount = (int) ($row[9] ?? 0);
            $authorId = (int) ($row[10] ?? 0);
            $moduleId = (int) ($row[11] ?? 0);
            $imageUrl = trim($row[12] ?? '');
            $articleUrl = trim($row[13] ?? '');
            $startDate = trim($row[14] ?? '');
            $endDate = trim($row[15] ?? '');

            // Skip invalid entries (empty title, ID=0, or title looks like a date due to CSV corruption)
            if (empty($title) || $legacyId === 0 || preg_match('/^\d{4}-\d{2}-\d{2}/', $title)) {
                $this->stats['skipped']++;
                continue;
            }

            // Get body from pages lookup
            $body = $pageLookup[$legacyId] ?? '';

            // Check if article already exists by title to avoid duplicates
            $existingNews = News::where('title', $title)->first();

            if ($existingNews) {
                $this->line("  [{$legacyId}] {$title} - already exists (ID: {$existingNews->id})");
                $this->stats['skipped']++;
                continue;
            }

            // Map author username
            $authorUsername = $this->getAuthorUsername($authorId);
            $authorName = $this->getAuthorDisplayName($authorId);

            // If author can't be determined, fall back to a default user
            if (empty($authorUsername)) {
                $fallbackUser = User::where('username', 'admin')->first()
                    ?? User::first();
                if ($fallbackUser) {
                    $authorUsername = $fallbackUser->username;
                    $authorName = $fallbackUser->name;
                    $this->line("  [{$legacyId}] {$title} - using fallback author '{$authorUsername}' (original ID: {$authorId})");
                } else {
                    $this->line("  [{$legacyId}] {$title} - skipped (no fallback author available)");
                    $this->stats['skipped']++;
                    continue;
                }
            }

            // Verify the author actually exists in the users table (foreign key constraint)
            if (!User::where('username', $authorUsername)->exists()) {
                $this->line("  [{$legacyId}] {$title} - skipped (author '{$authorUsername}' not found in DB)");
                $this->stats['skipped']++;
                continue;
            }

            // Determine status
            $status = 'draft';
            if ($isApproved && !$isDraft) {
                $status = 'published';
            } elseif ($isDraft) {
                $status = 'draft';
            }

            // Handle image URL
            $finalImageUrl = null;
            if (!empty($imageUrl)) {
                $finalImageUrl = $this->handleImageUrl($imageUrl, $legacyImagesStorage, $legacyImageFiles, $dryRun);
            }

            // If article has images in the image table, use the first one as the main image
            if (empty($finalImageUrl) && isset($imageLookup[$legacyId]) && !empty($imageLookup[$legacyId])) {
                $imgData = $imageLookup[$legacyId][0];
                $imgRelativePath = trim($imgData['folder'], '/') . '/' . $imgData['fileName'];
                $finalImageUrl = $this->handleImageLegacyPath($imgRelativePath, $legacyImagesDir, $legacyImagesStorage, $dryRun);
            }

            // Map category
            $categoryId = null;
            if (isset($categoryLookup[$legacyId]) && !empty($categoryLookup[$legacyId])) {
                $legacyCatId = $categoryLookup[$legacyId][0];
                $categoryName = $this->getCategoryNameById($legacyCatId);
                if ($categoryName) {
                    $category = NewsCategory::where('name', $categoryName)->first();
                    if ($category) {
                        $categoryId = $category->id;
                    }
                }
            }

            // Parse published_at
            $publishedAt = null;
            if (!empty($startDate) && $startDate !== 'NULL') {
                $publishedAt = $startDate;
            } elseif (!empty($createdDate) && $createdDate !== 'NULL') {
                $publishedAt = $createdDate;
            }

            $articleData = [
                'title' => $title,
                'summary' => !empty($summary) ? $summary : null,
                'content' => !empty(trim($body)) ? trim($body) : ($summary ?: $title),
                'category_id' => $categoryId,
                'author_username' => $authorUsername,
                'author_name' => $authorName,
                'author_role' => null,
                'image_url' => $finalImageUrl,
                'views_count' => $viewsCount,
                'likes_count' => 0,
                'is_pinned' => (bool) $isFeatured,
                'comments_enabled' => true,
                'status' => $status,
                'target_audience' => 'all',
                'tags' => null,
                'attachments' => null,
                'published_at' => $publishedAt,
            ];

            if (!$dryRun) {
                News::create($articleData);
            }

            $this->stats['articles']++;
            $this->line("  [{$legacyId}] {$title} ({$status})");
        }
    }

    private function importTags(string $csvDir, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Skipping Tags Import ---');
        $this->line('  Tags from legacy DNN are not imported directly.');
        $this->line('  The Laravel News model stores tags as a JSON array.');
    }

    /**
     * Get author username from the users CSV by legacy ID.
     */
    private function getAuthorUsername(int $legacyUserId): ?string
    {
        static $userMap = null;
        if ($userMap === null) {
            $userMap = [];
            $rows = $this->parseCsv('E:\\yazdrud.ir\\sqlserver\\users.csv');
            foreach ($rows as $row) {
                if (count($row) >= 4) {
                    $userMap[(int) $row[0]] = trim($row[1]);
                }
            }
        }
        return $userMap[$legacyUserId] ?? null;
    }

    /**
     * Get author display name from the users CSV by legacy ID.
     */
    private function getAuthorDisplayName(int $legacyUserId): ?string
    {
        static $nameMap = null;
        if ($nameMap === null) {
            $nameMap = [];
            $rows = $this->parseCsv('E:\\yazdrud.ir\\sqlserver\\users.csv');
            foreach ($rows as $row) {
                if (count($row) >= 4) {
                    $nameMap[(int) $row[0]] = trim($row[2] ?? $row[1]);
                }
            }
        }
        return $nameMap[$legacyUserId] ?? null;
    }

    /**
     * Get category name by legacy category ID.
     */
    private function getCategoryNameById(int $legacyCatId): ?string
    {
        static $catMap = null;
        if ($catMap === null) {
            $catMap = [];
            $rows = $this->parseCsv('E:\\yazdrud.ir\\sqlserver\\categories.csv');
            foreach ($rows as $row) {
                if (count($row) >= 3) {
                    $catMap[(int) $row[0]] = trim($row[2]);
                }
            }
        }
        return $catMap[$legacyCatId] ?? null;
    }

    /**
     * Handle an image URL from the legacy database.
     * If it's a path like /Portals/36/image.jpg, try to find the file in the legacy images storage.
     */
    private function handleImageUrl(string $imageUrl, string $legacyStorage, array $legacyFiles, bool $dryRun): ?string
    {
        // Extract filename from URL/path
        $filename = basename($imageUrl);

        // Try to find the file in legacy storage
        $foundFile = null;
        foreach ($legacyFiles as $file) {
            if (strtolower($file) === strtolower($filename)) {
                $foundFile = $file;
                break;
            }
        }

        if ($foundFile) {
            // Copy to proper media directory
            $sourcePath = $legacyStorage . '/' . $foundFile;
            $targetDir = 'media/' . date('Y/m');
            $newFilename = Str::uuid() . '.' . pathinfo($foundFile, PATHINFO_EXTENSION);

            if (!$dryRun) {
                $targetPath = $targetDir . '/' . $newFilename;
                Storage::disk('public')->put($targetPath, file_get_contents($sourcePath));
                $this->stats['images_copied']++;
            }

            return Storage::disk('public')->url($targetDir . '/' . $newFilename);
        }

        return null;
    }

    /**
     * Handle an image from the legacy image table (Folder + FileName).
     */
    private function handleImageLegacyPath(string $relativePath, string $legacyImagesDir, string $legacyStorage, bool $dryRun): ?string
    {
        // Normalize path separators
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        // Check in legacy website directory first
        $fullPath = $legacyImagesDir . DIRECTORY_SEPARATOR . $relativePath;
        if (file_exists($fullPath)) {
            $newFilename = Str::uuid() . '.' . pathinfo($fullPath, PATHINFO_EXTENSION);
            $targetDir = 'media/' . date('Y/m');

            if (!$dryRun) {
                $targetPath = $targetDir . '/' . $newFilename;
                Storage::disk('public')->put($targetPath, file_get_contents($fullPath));
                $this->stats['images_copied']++;
            }

            return Storage::disk('public')->url($targetDir . '/' . $newFilename);
        }

        // Check in legacy storage (copied files)
        $filename = basename($relativePath);
        $storageFile = $legacyStorage . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($storageFile)) {
            $newFilename = Str::uuid() . '.' . pathinfo($storageFile, PATHINFO_EXTENSION);
            $targetDir = 'media/' . date('Y/m');

            if (!$dryRun) {
                $targetPath = $targetDir . '/' . $newFilename;
                Storage::disk('public')->put($targetPath, file_get_contents($storageFile));
                $this->stats['images_copied']++;
            }

            return Storage::disk('public')->url($targetDir . '/' . $newFilename);
        }

        return null;
    }
}
