<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsPaginationMeta
{
    /**
     * Build a standardized pagination meta array.
     * Additional context keys (e.g. search, sort) can be merged via $context.
     */
    protected function buildPaginationMeta(LengthAwarePaginator $paginator, array $context = []): array
    {
        $base = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ];
        foreach ($context as $k => $v) {
            if ($v !== null) {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    /**
     * Convenience method to return a JSON paginated response.
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, $items, array $context = [], int $status = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => $this->buildPaginationMeta($paginator, $context),
        ], $status);
    }
}
