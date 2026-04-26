<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::create(['name' => 'Action']);
        Category::create(['name' => 'Drama']);
        Category::create(['name' => 'Comedy']);
        Category::create(['name' => 'Horror']);
    }
}
