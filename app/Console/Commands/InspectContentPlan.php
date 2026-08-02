<?php

namespace App\Console\Commands;

use App\Services\Content\ContentPlanDocxImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class InspectContentPlan extends Command
{
    protected $signature = 'content-plan:inspect {path}';

    protected $description = 'Inspect a DOCX content plan without saving it';

    public function handle(ContentPlanDocxImporter $importer): int
    {
        try {
            $payload = $importer->inspect((string) $this->argument('path'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info($payload['title']);
        $this->line('Posts: '.count($payload['posts']));
        $this->line('Design specifications: '.count($payload['design_specifications']));
        $this->line('Publishing specifications: '.count($payload['publishing_specifications']));
        $this->line('Activity rules: '.count($payload['activity_protocol']));
        $this->line('Safety rules: '.count($payload['safety_rules']));

        return self::SUCCESS;
    }
}
