<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Definitions;

use SiteBuilder\Core\Components\PropertyDefinition as P;
use SiteBuilder\Core\Content\Diagnostic;
use SiteBuilder\Core\Content\JsonObject;
use SiteBuilder\Core\Content\PropertyValues;
use SiteBuilder\Core\Contracts\ValidatesComponentValues;
use SiteBuilder\Core\Rendering\RenderContext;

final class Table extends LeafDefinition implements ValidatesComponentValues
{
    public function type(): string
    {
        return 'standard.table';
    }

    public function view(): string
    {
        return 'site-builder-components::blocks.table';
    }

    public function defaults(): PropertyValues
    {
        return new PropertyValues(props: ['row_headers' => true], translations: ['en' => ['caption' => 'Services at a glance', 'columns' => [['label' => 'Service'], ['label' => 'What is included']], 'rows' => [['cells' => [['body' => '<p>First conversation</p>'], ['body' => '<p>Listen, understand and agree a next step.</p>']]], ['cells' => [['body' => '<p>Working session</p>'], ['body' => '<p>Practical help with your next challenge.</p>']]]]]]);
    }

    public function properties(): array
    {
        return ['row_headers' => new P('boolean'), 'caption' => P::text(translated: true, requiredForPublish: true), 'columns' => new P('list', translated: true, rules: ['min:1', 'max:12'], items: P::object(['label' => P::text(requiredForPublish: true)])), 'rows' => new P('list', translated: true, rules: ['max:100'], items: P::object(['cells' => new P('list', rules: ['min:1', 'max:12'], items: P::object(['body' => P::html()]))]))];
    }

    public function diagnostics(JsonObject $values, RenderContext $context, bool $publishing): array
    {
        if (! $publishing) {
            return [];
        }
        $columns = count($values->get('columns', []) ?? []);
        $errors = [];
        foreach ($values->get('rows', []) ?? [] as $index => $row) {
            if ($row instanceof \stdClass && count($row->cells ?? []) !== $columns) {
                $errors[] = new Diagnostic('table_column_mismatch', '/rows/'.$index, 'Each table row must have one cell per column.');
            }
        }

        return $errors;
    }
}
