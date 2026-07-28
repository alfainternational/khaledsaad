<?php

namespace Database\Seeders;

use App\Modules\Intake\Catalog\ConsultationCatalogBuilder;
use Illuminate\Database\Seeder;

class ConsultationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(ConsultationCatalogBuilder::class)->publishDefault();
    }
}
