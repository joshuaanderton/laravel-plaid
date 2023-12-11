<?php

namespace Ja\Plaid\Actions;

use App\Models\Category;
use App\Actions\Action;
use Illuminate\Support\Collection;
use TomorrowIdeas\Plaid\Plaid;

class ImportCategories extends Action
{
    public function handle(): Collection
    {
        $plaid = new Plaid(
            env('PLAID_CLIENT_ID'),
            env('PLAID_SECRET_KEY'),
            env('PLAID_ENV')
        );

        $cats = $plaid->categories->list()->categories;
        $cats = collect($plaid->categories->list()->categories);

        return $cats->map(function ($cat) use ($cats) {
            $hierarchy = array_reverse($cat->hierarchy);
            $name = $hierarchy[0];
            $parentCategory = null;

            if ($parentName = $hierarchy[1] ?? null) {
                $parentId = $cats->filter(fn ($pCat) => array_reverse($pCat->hierarchy)[0] === $parentName)->first()->category_id;

                $parentCategory = Category::firstOrCreate(['plaid_category_id' => $parentId], [
                    'name' => $parentName,
                ]);
            }

            return Category::updateOrCreate(['plaid_category_id' => $cat->category_id], [
                'name' => $name,
                'parent_category_id' => $parentCategory->id ?? null,
                'hierarchy' => implode(' > ', $cat->hierarchy),
            ]);
        });
    }
}
