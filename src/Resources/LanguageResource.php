<?php

namespace AnyMedia\Interpresso\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use AnyMedia\Interpresso\Models\Language;

/**
 * @mixin Language
 */
class LanguageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Language $language */
        $language = $this->resource;

        return [
            'id' => $language->id,
            'name' => $language->name,
            'native_name' => $language->native_name,
            'code' => $language->code,
            'created_at' => $language->created_at?->toDateTimeString(),
            'updated_at' => $language->updated_at?->toDateTimeString()
        ];
    }

}
