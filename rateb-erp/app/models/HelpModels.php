<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class HelpArticle extends Model
{
    protected string $table = 'rateb_help_articles';
    protected array $fillable = [
        'category_id', 'module_slug', 'slug', 'title_en', 'title_ar',
        'summary_en', 'summary_ar', 'body_json_en', 'body_json_ar',
        'difficulty', 'minutes', 'icon', 'audience', 'route_hint',
        'keywords_json', 'related_json', 'sort_order', 'status',
    ];
    protected array $searchable = ['slug', 'title_en', 'title_ar', 'summary_en', 'summary_ar', 'module_slug'];
}

final class HelpCategory extends Model
{
    protected string $table = 'rateb_help_categories';
    protected array $fillable = [
        'slug', 'title_en', 'title_ar', 'description_en', 'description_ar',
        'icon', 'accent', 'module_gate', 'audience', 'sort_order', 'status',
    ];
}
