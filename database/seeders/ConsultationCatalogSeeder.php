<?php

namespace Database\Seeders;

use App\Services\Consultations\Catalog\ConsultationCatalogBuilder;
use Illuminate\Database\Seeder;

class ConsultationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(ConsultationCatalogBuilder::class)->publishDefault();
    }
}
