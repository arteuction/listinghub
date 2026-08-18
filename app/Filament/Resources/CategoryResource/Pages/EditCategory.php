<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Actions\Categories\DeleteCategory as DeleteCategoryAction;
use App\Actions\Categories\UpdateCategory as UpdateCategoryAction;
use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Category $record): void {
                    app(DeleteCategoryAction::class)->handle($record);
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Category $record */
        app(UpdateCategoryAction::class)->handle($record, $data);

        return $record->refresh();
    }
}
