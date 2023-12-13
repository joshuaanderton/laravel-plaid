<?php

namespace Ja\LaravelPlaid\Actions;

use App\Models\Category;
use App\Actions\Action;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class ImportCategories extends Action
{
    public function handle(): Collection
    {
        $csvData = file(__DIR__.'/../../transactions-personal-finance-category-taxonomy.csv');
        $cats = collect($csvData)->slice(1)->map(fn ($line) => str_getcsv($line));

        return $cats->map(function ($cat) use ($cats) {

            list($primary, $detailed, $description) = $cat;

            $name = Str::replace('_', ' ', Str::title(Str::remove($primary.'_', $detailed)));

            if ($cats->filter(fn ($c) => $detailed !== $c[1] && Str::remove($primary.'_', $detailed) === Str::remove($c[0].'_', $c[1]))->count() > 0) {
                $name.= ' ('.Str::replace('_', ' ', Str::title($primary)).')';
            }

            $category = Category::updateOrCreate([
                'plaid_category_detailed' => $detailed
            ], [
                'name' => $name,
                'slug' => Str::replace('_', '-', Str::lower($detailed)),
                'description' => $description,
            ]);

            return $category;
        });
    }
}
