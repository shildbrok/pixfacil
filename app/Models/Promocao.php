<?php

namespace App\Models;

use App\Support\PromotionHtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocao extends Model
{
    use HasFactory;

    protected $table = 'promocoes';

    protected $fillable = [
        'titulo',
        'imagem',
        'link',
        'regras_html',
    ];

    protected function regrasHtml(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => PromotionHtmlSanitizer::sanitize($value),
            set: fn (?string $value) => PromotionHtmlSanitizer::sanitize($value),
        );
    }
}
