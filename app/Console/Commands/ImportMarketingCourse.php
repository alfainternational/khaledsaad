<?php

namespace App\Console\Commands;

use App\Services\Content\MarketingCourseImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportMarketingCourse extends Command
{
    protected $signature = 'content:import-marketing-course
                            {--publish : Publish every lesson immediately}
                            {--force : Allow publishing while the application is in production}';

    protected $description = 'Import or refresh the versioned marketing learning magazine';

    public function handle(MarketingCourseImporter $importer): int
    {
        if ($this->option('publish') && app()->environment('production') && ! $this->option('force')) {
            $this->error('Use --force to publish the marketing course in production.');

            return self::FAILURE;
        }

        try {
            $result = $importer->import((bool) $this->option('publish'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d lessons imported: %d created, %d updated.',
            $result['total'],
            $result['created'],
            $result['updated'],
        ));

        return self::SUCCESS;
    }
}
