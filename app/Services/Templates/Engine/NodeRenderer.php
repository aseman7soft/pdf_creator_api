<?php

declare(strict_types=1);

namespace App\Services\Templates\Engine;

use App\Exceptions\TemplateRenderException;

/**
 * Renders a compiled template AST against a data context.
 *
 * Escaping contract: every `{{ }}` output is HTML-escaped unless a filter
 * explicitly produced safe markup (`nl2br`), which itself escapes its input
 * before inserting `<br>` tags. Nothing else can emit raw HTML.
 */
final class NodeRenderer
{
    public function __construct(
        private readonly ExpressionEvaluator $expressions = new ExpressionEvaluator,
    ) {}

    /**
     * @param  list<Node>  $nodes
     * @param  array<string, mixed>  $context
     */
    public function render(array $nodes, array $context): string
    {
        $output = '';

        foreach ($nodes as $node) {
            $output .= match ($node->kind) {
                Node::KIND_TEXT => $node->text,
                Node::KIND_OUTPUT => $this->renderOutput($node, $context),
                Node::KIND_IF => $this->renderIf($node, $context),
                Node::KIND_FOREACH => $this->renderForeach($node, $context),
                Node::KIND_CONDITION => $this->renderCondition($node, $context),
                default => throw new TemplateRenderException('نوع گره قالب پشتیبانی نمی‌شود.', $node->line),
            };
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderOutput(Node $node, array $context): string
    {
        try {
            $value = $this->expressions->evaluate($node->expr, $context);
            $filtered = $this->expressions->applyFilters($value, $node->filters, $context);
        } catch (TemplateRenderException $exception) {
            throw $exception->withLine($node->line);
        }

        if ($filtered['safe']) {
            return $filtered['value'];
        }

        return $this->escape($filtered['value']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderIf(Node $node, array $context): string
    {
        foreach ($node->branches as $branch) {
            try {
                $matched = (bool) $this->expressions->evaluate($branch['expr'], $context);
            } catch (TemplateRenderException $exception) {
                throw $exception->withLine($node->line);
            }

            if ($matched) {
                return $this->render($branch['children'], $context);
            }
        }

        return $this->render($node->children, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderForeach(Node $node, array $context): string
    {
        try {
            $items = $this->expressions->evaluate($node->expr, $context);
        } catch (TemplateRenderException $exception) {
            throw $exception->withLine($node->line);
        }

        if ($items instanceof \Traversable) {
            $items = iterator_to_array($items, true);
        }

        if (! is_array($items) || $items === []) {
            return '';
        }

        $count = count($items);
        $index = 0;
        $output = '';

        foreach ($items as $key => $item) {
            $iteration = $index + 1;
            $children = $context;
            $children['loop'] = [
                'index' => $index,
                'iteration' => $iteration,
                'first' => $index === 0,
                'last' => $index === $count - 1,
                'count' => $count,
                'remaining' => $count - $iteration,
            ];

            if ($node->keyVariable !== null) {
                $children[$node->keyVariable] = $key;
            }

            if ($node->valueVariable !== null) {
                $children[$node->valueVariable] = $item;
            }

            $output .= $this->render($node->children, $children);
            $index++;
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderCondition(Node $node, array $context): string
    {
        try {
            $value = $this->expressions->evaluate($node->expr, $context);
        } catch (TemplateRenderException $exception) {
            throw $exception->withLine($node->line);
        }

        $matched = $node->conditionMode === 'isset'
            ? $value !== null
            : ($value === null || $value === '' || $value === false || $value === 0 || $value === 0.0 || $value === '0' || $value === []);

        if (! $matched) {
            return '';
        }

        return $this->render($node->children, $context);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
